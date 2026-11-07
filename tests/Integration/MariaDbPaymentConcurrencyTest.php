<?php

namespace Tests\Integration;

use App\Models\InvoiceReceivable;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;
use App\Services\Invoicing\InvoiceVersion;
use App\Services\Payments\PaymentQueries;
use App\Services\Payments\PaymentWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class MariaDbPaymentConcurrencyTest extends TestCase
{
    public function test_additive_upgrade_and_real_payment_row_locks(): void
    {
        $database = getenv('BIZPILOT_M4_TEST_DATABASE');
        if (! $database || ! preg_match('/^bizpilot_m4_qa_\d{8}_[a-f0-9]{6}$/D', $database) || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires a freshly provisioned isolated M4 QA schema and pcntl.');
        }
        $this->assertNotSame(config('database.connections.mysql.database'), $database);
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null, 'bizpilot.payments_enabled' => true, 'logging.default' => 'null']);
        DB::purge('mysql');
        $this->assertSame($database, DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->assertSame(0, DB::table('users')->count());
        $migration = require database_path('migrations/2026_11_02_000001_create_payment_ledger_tables.php');
        $migration->up();
        $f = BusinessFixture::seed();
        $before = $f['invoice']->fresh()->getAttributes();
        $writer = app(PaymentWriter::class);
        $writer->review($f['vendor'], $f['invoice'], ['action' => 'confirm', 'original_version' => app(InvoiceVersion::class)->token($f['invoice']->fresh('items')), 'review_note' => 'Synthetic migration and concurrency review', 'due_date' => null]);
        $payload = ['idempotency_key' => Str::uuid()->toString(), 'customer_id' => $f['customer']->id, 'payment_date' => today()->toDateString(), 'amount' => '500.25', 'method' => 'upi', 'reference' => 'SYNTHETIC-RACE', 'allocations' => [['invoice_id' => 1, 'amount' => '500.25']]];
        $same = $this->race($f['vendor']->id, [['create', $payload], ['create', $payload]]);
        $this->assertSame(2, count(array_filter($same, fn ($r) => $r['ok'])));
        $this->assertSame(1, count(array_filter($same, fn ($r) => $r['replayed'] ?? false)));
        $this->assertSame(1, Payment::count());
        $a = $payload;
        $a['idempotency_key'] = Str::uuid()->toString();
        $b = $a;
        $b['idempotency_key'] = Str::uuid()->toString();
        $last = $this->race($f['vendor']->id, [['create', $a], ['create', $b]]);
        $this->assertSame(1, count(array_filter($last, fn ($r) => $r['ok'])));
        $this->assertSame(1, count(array_filter($last, fn ($r) => isset($r['errors']['allocations']))));
        $this->assertSame(2, Payment::count());
        $this->assertSame(17950, app(PaymentQueries::class)->confirmedTotals($f['vendor']->id)['outstanding']);
        $reverse = ['id' => Payment::first()->id, 'reversal_date' => today()->toDateString(), 'reason' => 'Synthetic concurrent reversal'];
        $results = $this->race($f['vendor']->id, [['reverse', $reverse], ['reverse', $reverse]]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertSame(1, count(array_filter($results, fn ($r) => isset($r['errors']['reversal']))));
        $this->assertSame(1, PaymentReversal::count());
        $this->assertSame(67975, app(PaymentQueries::class)->confirmedTotals($f['vendor']->id)['outstanding']);
        $this->assertSame($before, $f['invoice']->fresh()->getAttributes());
        $this->assertSame(1, InvoiceReceivable::count());
        $ledger = app(PaymentQueries::class)->ledger($f['vendor']->id, $f['customer']->id)->get();
        $this->assertCount(4, $ledger);
        $this->assertSame(67975, (int) $ledger->last()->balance);
        $this->assertSame(['gross' => 100050, 'reversed' => 50025, 'net' => 50025], app(PaymentQueries::class)->collections($f['vendor']->id, []));
    }

    private function race(int $vendorId, array $jobs): array
    {
        $base = sys_get_temp_dir().'/bizpilot-m4-race-'.bin2hex(random_bytes(8));
        $start = $base.'.start';
        $pids = [];
        DB::disconnect();
        foreach ($jobs as $index => [$operation,$data]) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Could not fork QA worker.');
            }
            if ($pid === 0) {
                DB::purge('mysql');
                $paused = false;
                DB::listen(function ($query) use (&$paused) {
                    if (! $paused && str_contains(strtolower($query->sql), 'for update') && str_contains($query->sql, 'users')) {
                        $paused = true;
                        usleep(200000);
                    }
                });
                for ($n = 0; $n < 500 && ! is_file($start); $n++) {
                    usleep(10000);
                }
                try {
                    $vendor = User::findOrFail($vendorId);
                    if ($operation === 'create') {
                        $result = app(PaymentWriter::class)->create($vendor, $data);
                        $out = ['ok' => true, 'replayed' => $result['replayed']];
                    } else {
                        app(PaymentWriter::class)->reverse($vendor, Payment::findOrFail($data['id']), $data);
                        $out = ['ok' => true];
                    }
                } catch (ValidationException $e) {
                    $out = ['ok' => false, 'errors' => $e->errors()];
                } catch (\Throwable $e) {
                    $out = ['ok' => false, 'exception' => get_class($e)];
                }
                file_put_contents($base.'.'.$index.'.json', json_encode($out));
                DB::disconnect();
                exit(0);
            }
            $pids[] = $pid;
        }
        touch($start);
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge('mysql');
        $results = [];
        foreach ($jobs as $index => $job) {
            $path = $base.'.'.$index.'.json';
            $this->assertFileExists($path);
            $results[] = json_decode(file_get_contents($path), true);
            unlink($path);
        }
        unlink($start);

        return $results;
    }
}
