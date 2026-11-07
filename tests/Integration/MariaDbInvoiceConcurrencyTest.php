<?php

namespace Tests\Integration;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoicing\InvoiceVersion;
use App\Services\Invoicing\InvoiceWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class MariaDbInvoiceConcurrencyTest extends TestCase
{
    public function test_real_row_locks_serialize_replays_quota_and_stale_edits(): void
    {
        $database = getenv('BIZPILOT_M3_TEST_DATABASE');
        if (! $database || ! preg_match('/^bizpilot_m3_qa_\d{8}_[a-f0-9]{6}$/D', $database) || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires explicitly created isolated MariaDB QA schema and pcntl.');
        }
        $original = config('database.connections.mysql.database');
        $this->assertNotSame($original, $database);
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null, 'logging.default' => 'null', 'filesystems.disks.local.root' => sys_get_temp_dir().'/bizpilot-m3-concurrency-archives']);
        DB::purge('mysql');
        $this->assertSame($database, DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->assertSame(0, DB::table('users')->count(), 'Only an empty agent-created QA database may be seeded.');
        $f = BusinessFixture::seed();
        $p = ['invoice_no' => 'GT/002', 'invoice_date' => today()->toDateString(), 'customer_id' => $f['customer']->id, 'discount_percent' => '10', 'notes' => 'Race test', 'items' => [['item_name' => 'Synthetic item', 'qty' => '100', 'rate' => '10', 'cgst_percent' => '9', 'sgst_percent' => '9']]];
        $same = $this->race($f['vendor']->id, [['create', $p], ['create', $p]]);
        $this->assertSame(2, count(array_filter($same, fn ($r) => $r['ok'])));
        $this->assertSame(1, count(array_filter($same, fn ($r) => $r['replayed'] ?? false)));
        $this->assertSame(2, Invoice::count());
        $this->assertSame(2, (int) $f['subscription']->fresh()->invoice_used);
        $f['plan']->update(['invoice_limit' => 3]);
        $a = $p;
        $a['invoice_no'] = 'GT/003';
        $b = $p;
        $b['invoice_no'] = 'GT/004';
        $quota = $this->race($f['vendor']->id, [['create', $a], ['create', $b]]);
        $this->assertSame(1, count(array_filter($quota, fn ($r) => $r['ok'])));
        $this->assertSame(1, count(array_filter($quota, fn ($r) => isset($r['errors']['subscription']))));
        $this->assertSame(3, Invoice::count());
        $this->assertSame(3, (int) $f['subscription']->fresh()->invoice_used);
        $i = $f['invoice']->fresh('items');
        $edit = array_merge($i->getAttributes(), ['items' => $i->items->toArray(), 'original_version' => app(InvoiceVersion::class)->token($i)]);
        $a = $edit;
        $a['notes'] = 'Concurrent note A';
        $b = $edit;
        $b['notes'] = 'Concurrent note B';
        $edits = $this->race($f['vendor']->id, [['update', $a], ['update', $b]]);
        $this->assertSame(1, count(array_filter($edits, fn ($r) => $r['ok'])));
        $this->assertSame(1, count(array_filter($edits, fn ($r) => isset($r['errors']['original_version']))));
        $this->assertSame('1180.00', $i->fresh()->grand_total);
    }

    private function race(int $vendorId, array $jobs): array
    {
        $base = sys_get_temp_dir().'/bizpilot-m3-race-'.bin2hex(random_bytes(8));
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
                        $result = app(InvoiceWriter::class)->create($vendor, $data);
                        $out = ['ok' => true, 'replayed' => $result['replayed']];
                    } else {
                        app(InvoiceWriter::class)->update($vendor, Invoice::findOrFail($data['id']), $data);
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
