<?php

namespace Tests\Integration;

use App\Models\BillingRecord;
use App\Models\InventorySetting;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Services\Business\Catalog;
use App\Services\Business\ManualSubscriptions;
use App\Services\Business\QuotationWriter;
use App\Services\Invoicing\InvoiceWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class MariaDbModulesConcurrencyTest extends TestCase
{
    public function test_additive_upgrade_and_real_payment_row_locks(): void
    {
        $database = getenv('BIZPILOT_MODULES_TEST_DATABASE');
        if (! $database || ! preg_match('/^bizpilot_m5_qa_\d{8}_[a-f0-9]{6}$/D', $database) || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires a freshly provisioned isolated M4 QA schema and pcntl.');
        }
        $this->assertNotSame(config('database.connections.mysql.database'), $database);
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null, 'bizpilot.payments_enabled' => true, 'logging.default' => 'null']);
        DB::purge('mysql');
        $this->assertSame($database, DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->assertSame(0, DB::table('users')->count());
        foreach (['2026_11_02_000001_create_payment_ledger_tables', '2026_11_02_000002_create_business_modules_tables', '2026_11_02_000003_create_platform_services_tables'] as $m) {
            (require database_path('migrations/'.$m.'.php'))->up();
        }
        config(['bizpilot.business_modules_enabled' => true, 'bizpilot.platform_enabled' => true]);
        $f = BusinessFixture::seed();
        $before = $f['invoice']->fresh()->getAttributes();
        $p = Product::create(['vendor_id' => 2, 'name' => 'Synthetic product', 'sku' => 'QA-1', 'kind' => 'product', 'unit' => 'piece', 'selling_price' => '100.00', 'purchase_price' => '60.00', 'cgst_percent' => '9', 'sgst_percent' => '9', 'is_active' => true, 'track_stock' => true, 'low_stock_level' => '0']);
        InventorySetting::create(['vendor_id' => 2, 'enabled' => true]);
        $catalog = app(Catalog::class);
        $catalog->adjust($f['vendor'], $p, ['quantity' => '1.00', 'direction' => 'in', 'reason' => 'Synthetic opening', 'request_key' => Str::uuid()->toString()]);
        $line = ['item_name' => 'Synthetic product', 'product_id' => $p->id, 'qty' => '1.00', 'rate' => '100.00', 'hsn_sac' => '5205', 'cgst_percent' => '9', 'sgst_percent' => '9'];
        $data = ['customer_id' => 4, 'invoice_date' => today()->toDateString(), 'discount_percent' => '0', 'items' => [$line], 'invoice_no' => 'GT/002'];
        $other = $data;
        $other['invoice_no'] = 'GT/003';
        $r = $this->race(2, [['invoice', $data], ['invoice', $other]]);
        $this->assertSame(1, count(array_filter($r, fn ($x) => $x['ok'])), json_encode($r));
        $this->assertSame(0, $catalog->stock($p->id));
        $this->assertSame(1, StockMovement::where('kind', 'invoice_sale')->count());
        unset($line['product_id']);
        $qw = app(QuotationWriter::class);
        $q = $qw->save($f['vendor'], ['customer_id' => 4, 'quotation_date' => today()->toDateString(), 'valid_until' => today()->addDays(10)->toDateString(), 'discount_percent' => '0', 'items' => [$line]]);
        $q = $qw->status($f['vendor'], $q, 'sent', $q->revision, 'Synthetic');
        $q = $qw->status($f['vendor'], $q, 'accepted', $q->revision, 'Synthetic');
        $r = $this->race(2, [['convert', ['id' => $q->id, 'revision' => $q->revision]], ['convert', ['id' => $q->id, 'revision' => $q->revision]]]);
        $this->assertSame(2, count(array_filter($r, fn ($x) => $x['ok'])), json_encode($r));
        $this->assertSame($r[0]['id'], $r[1]['id']);
        $sub = Subscription::where('user_id', 2)->where('is_active', true)->first();
        $d = ['subscription_plan_id' => $f['plan']->id, 'expected_active_id' => $sub->id, 'expected_end_date' => Carbon::parse($sub->end_date)->toDateString(), 'mode' => 'extend', 'reason' => 'Synthetic concurrent extension', 'request_key' => Str::uuid()->toString(), 'billing_amount' => '0'];
        $r = $this->race(2, [['subscription', $d], ['subscription', $d]]);
        $this->assertSame(2, count(array_filter($r, fn ($x) => $x['ok'])), json_encode($r));
        $this->assertSame(1, SubscriptionEvent::count());
        $this->assertSame(0, BillingRecord::count());
        $this->assertSame($before, $f['invoice']->fresh()->getAttributes());
        $analytics = app(\App\Services\Business\Analytics::class)->summary(2, ['from_date'=>today()->startOfMonth()->toDateString(), 'to_date'=>today()->toDateString()]);
        $this->assertGreaterThan(0, (int)$analytics['cost']->line_count);
        $this->assertNull($analytics['profit']);
    }

    private function race(int $vendorId, array $jobs): array
    {
        $base = sys_get_temp_dir().'/bizpilot-modules-race-'.bin2hex(random_bytes(8));
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
                    if ($operation === 'invoice') {
                        $i = app(InvoiceWriter::class)->create($vendor, $data);
                        $out = ['ok' => true];
                    } elseif ($operation === 'convert') {
                        $i = app(QuotationWriter::class)->convert($vendor, Quotation::findOrFail($data['id']), $data['revision']);
                        $out = ['ok' => true, 'id' => $i->id];
                    } else {
                        app(ManualSubscriptions::class)->assign(User::findOrFail(1), $vendor, $data);
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
