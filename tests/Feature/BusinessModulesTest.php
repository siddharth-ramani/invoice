<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\InventorySetting;
use App\Models\Invoice;
use App\Models\InvoiceProductLink;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\StockMovement;
use App\Services\Business\Analytics;
use App\Services\Business\Catalog;
use App\Services\Business\Guard;
use App\Services\Invoicing\InvoiceVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class BusinessModulesTest extends TestCase
{
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'bizpilot.business_modules_enabled' => true, 'bizpilot.payments_enabled' => true, 'logging.default' => 'null']);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $views = sys_get_temp_dir().'/bizpilot-full-test-views';
        if (! is_dir($views)) {
            mkdir($views, 0700, true);
        }config(['view.compiled' => $views]);
        Storage::fake('local');
        BusinessFixture::createSchema();
        $this->f = BusinessFixture::seed();
        (require database_path('migrations/2026_11_02_000001_create_payment_ledger_tables.php'))->up();
        (require database_path('migrations/2026_11_02_000002_create_business_modules_tables.php'))->up();
        $this->actingAs($this->f['vendor']);
    }

    private function product(array $extra = []): Product
    {
        $data = array_merge(['name' => 'QA material', 'sku' => null, 'kind' => 'product', 'unit' => 'piece', 'selling_price' => '100.00', 'purchase_price' => '60.00', 'cgst_percent' => '9', 'sgst_percent' => '9', 'is_active' => true, 'track_stock' => false, 'low_stock_level' => '2.00'], $extra);
        $this->post('/products', $data)->assertSessionHasNoErrors();

        return Product::latest('id')->firstOrFail();
    }

    private function invoiceData(?Product $product = null, string $number = 'GT/002'): array
    {
        return ['invoice_no' => $number, 'customer_id' => 4, 'invoice_date' => today()->toDateString(), 'discount_percent' => '0', 'items' => [['item_name' => 'QA line', 'hsn_sac' => '5205', 'qty' => '1.00', 'rate' => '100.00', 'cgst_percent' => '9', 'sgst_percent' => '9', 'product_id' => $product?->id]]];
    }

    private function quoteData(?Product $p = null): array
    {
        $data = $this->invoiceData($p);
        unset($data['invoice_no'],$data['invoice_date']);

        return array_merge($data, ['quotation_date' => today()->toDateString(), 'valid_until' => today()->addDays(30)->toDateString(), 'notes' => 'Synthetic offer']);
    }

    private function quote(?Product $p = null): Quotation
    {
        $this->post('/quotations', $this->quoteData($p))->assertSessionHasNoErrors();

        return Quotation::latest('id')->firstOrFail();
    }

    private function accept(Quotation $q): Quotation
    {
        foreach (['sent', 'accepted'] as $status) {
            $this->post('/quotations/'.$q->id.'/status', ['status' => $status, 'revision' => $q->fresh()->revision, 'reason' => 'Customer decision verified'])->assertSessionHasNoErrors();
        }

        return $q->fresh();
    }

    private function expenseData(): array
    {
        $category = ExpenseCategory::firstOrCreate(['vendor_id' => 2, 'name' => 'Utilities']);

        return ['expense_category_id' => $category->id, 'expense_date' => today()->toDateString(), 'amount' => '10.25', 'method' => 'upi', 'reference' => 'QA-BILL', 'notes' => 'Actual synthetic cost', 'request_key' => Str::uuid()->toString()];
    }

    public function test_quote_status_conversion_replay_and_history_preserve_invoice_numbering(): void
    {
        $q = $this->quote();
        $before = $this->f['invoice']->fresh()->getAttributes();
        $q = $this->accept($q);
        $data = ['revision' => $q->revision, 'confirm_conversion' => '1'];
        $this->post('/quotations/'.$q->id.'/convert', $data)->assertSessionHasNoErrors();
        $id = $q->fresh()->invoice_id;
        $this->post('/quotations/'.$q->id.'/convert', $data)->assertRedirect(route('invoices.show', $id));
        $this->assertSame(2, Invoice::count());
        $this->assertSame('GT/002', Invoice::find($id)->invoice_no);
        $this->assertSame(2, $this->f['subscription']->fresh()->invoice_used);
        $this->assertSame($before, $this->f['invoice']->fresh()->getAttributes());
        $this->assertSame('converted', $q->fresh()->status);
        $this->assertGreaterThanOrEqual(4, $q->events()->count());
        $version = app(InvoiceVersion::class)->token(Invoice::find($id));
        $this->delete('/invoices/'.$id, ['original_version' => $version])->assertSessionHasErrors('invoice');
    }

    public function test_draft_versions_and_status_transitions_reject_stale_and_unaccepted_conversions(): void
    {
        $q = $this->quote();
        $this->post('/quotations/'.$q->id.'/convert', ['revision' => 1, 'confirm_conversion' => 1])->assertSessionHasErrors('quotation');
        $this->post('/quotations/'.$q->id.'/status', ['revision' => 1, 'status' => 'accepted', 'reason' => 'Invalid transition'])->assertSessionHasErrors('status');
        $data = $this->quoteData();
        $data['revision'] = 1;
        $data['notes'] = 'Updated draft';
        $this->put('/quotations/'.$q->id, $data)->assertSessionHasNoErrors();
        $this->put('/quotations/'.$q->id, $data)->assertSessionHasErrors('revision');
        $this->assertSame('Updated draft', $q->fresh()->notes);
        $this->assertSame(1, Invoice::count());
    }

    public function test_duplicate_is_a_new_draft_and_expired_offers_cannot_convert(): void
    {
        $q = $this->quote();
        $q = $this->accept($q);
        $q->update(['valid_until' => today()->subDay()->toDateString()]);
        $this->post('/quotations/'.$q->id.'/convert', ['revision' => $q->revision, 'confirm_conversion' => 1])->assertSessionHasErrors('quotation');
        $this->post('/quotations/'.$q->id.'/duplicate')->assertSessionHasNoErrors();
        $copy = Quotation::latest('id')->first();
        $this->assertNotSame($q->id, $copy->id);
        $this->assertSame('draft', $copy->status);
        $this->assertSame($q->totals, $copy->totals);
        $this->assertNull($copy->invoice_id);
    }

    public function test_foreign_business_cannot_read_write_convert_or_export_quote_product_expense(): void
    {
        $p = $this->product();
        $q = $this->quote($p);
        $this->post('/expenses', $this->expenseData())->assertSessionHasNoErrors();
        $expense = Expense::first();
        $this->actingAs($this->f['otherVendor']);
        foreach (['/quotations/'.$q->id, '/quotations/'.$q->id.'/pdf', '/quotations/'.$q->id.'/edit', '/products/'.$p->id.'/edit', '/inventory/'.$p->id, '/expenses/'.$expense->id, '/expenses/'.$expense->id.'/edit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/quotations/'.$q->id.'/duplicate')->assertForbidden();
        $this->post('/quotations/'.$q->id.'/convert', [])->assertForbidden();
        $this->get('/products/lookup?q=QA')->assertJsonCount(0, 'items');
        $this->post('/inventory/'.$p->id.'/adjust', [])->assertForbidden();
    }

    public function test_catalog_costs_and_prices_are_snapshots_and_manual_items_still_work(): void
    {
        $p = $this->product();
        $data = $this->invoiceData($p);
        $this->post('/invoices', $data)->assertSessionHasNoErrors();
        $invoice = Invoice::latest('id')->first();
        $link = InvoiceProductLink::first();
        $this->assertSame('60.00', $link->cost_rate);
        $this->assertSame('piece', $link->unit);
        $p->update(['selling_price' => '200', 'purchase_price' => '90']);
        $this->assertSame(100, (int) $invoice->items()->first()->rate);
        $this->assertSame('60.00', $link->fresh()->cost_rate);
        $this->post('/invoices', $data)->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame(2, Invoice::count());
        $this->post('/invoices', $this->invoiceData(null, 'GT/003'))->assertSessionHasNoErrors();
        $this->assertSame(1, InvoiceProductLink::count());
    }

    public function test_inventory_prevents_negative_stock_and_invoice_failure_rolls_back_everything(): void
    {
        $p = $this->product(['track_stock' => true]);
        InventorySetting::create(['vendor_id' => 2, 'enabled' => true]);
        $before = $this->f['subscription']->invoice_used;
        $this->post('/invoices', $this->invoiceData($p))->assertSessionHasErrors('items');
        $this->assertSame(1, Invoice::count());
        $this->assertSame($before, $this->f['subscription']->fresh()->invoice_used);
        $this->assertSame(0, InvoiceProductLink::count());
        $key = Str::uuid()->toString();
        $adjust = ['direction' => 'in', 'quantity' => '5.25', 'reason' => 'Verified opening stock', 'request_key' => $key];
        $this->post('/inventory/'.$p->id.'/adjust', $adjust)->assertSessionHasNoErrors();
        $this->post('/inventory/'.$p->id.'/adjust', $adjust)->assertSessionHasNoErrors();
        $this->assertSame(1, StockMovement::count());
        $this->post('/invoices', $this->invoiceData($p))->assertSessionHasNoErrors();
        $this->assertSame(425, app(Catalog::class)->stock($p->id));
        $this->post('/inventory/'.$p->id.'/adjust', ['direction' => 'out', 'quantity' => '5', 'reason' => 'Too much stock out', 'request_key' => Str::uuid()->toString()])->assertSessionHasErrors('quantity');
        $this->assertSame(425, app(Catalog::class)->stock($p->id));
    }

    public function test_invoice_edit_and_soft_delete_reverse_stock_once_and_retain_cost_snapshot(): void
    {
        $p = $this->product(['track_stock' => true]);
        InventorySetting::create(['vendor_id' => 2, 'enabled' => true]);
        $this->post('/inventory/'.$p->id.'/adjust', ['direction' => 'in', 'quantity' => '10', 'reason' => 'Verified stock count', 'request_key' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->post('/invoices', $this->invoiceData($p))->assertSessionHasNoErrors();
        $invoice = Invoice::latest('id')->first()->load('items');
        $data = $this->invoiceData($p);
        $data['original_version'] = app(InvoiceVersion::class)->token($invoice);
        $data['confirm_recalculation'] = 1;
        $data['items'][0]['qty'] = '2';
        $this->put('/invoices/'.$invoice->id, $data)->assertSessionHasNoErrors();
        $this->assertSame(800, app(Catalog::class)->stock($p->id));
        $this->delete('/invoices/'.$invoice->id, ['original_version' => app(InvoiceVersion::class)->token($invoice->fresh('items'))])->assertSessionHasNoErrors();
        $this->assertSame(1000, app(Catalog::class)->stock($p->id));
        $this->assertSame(1, InvoiceProductLink::count());
        $this->assertSame(5, StockMovement::count());
    }

    public function test_service_catalog_does_not_force_inventory_and_units_lock_after_quote_use(): void
    {
        $p = $this->product(['kind' => 'service', 'unit' => 'hour', 'track_stock' => true]);
        $this->assertFalse($p->track_stock);
        InventorySetting::create(['vendor_id' => 2, 'enabled' => true]);
        $this->post('/invoices', $this->invoiceData($p))->assertSessionHasNoErrors();
        $this->assertSame(0, StockMovement::count());
        $q = $this->quote($p);
        $data = $p->getAttributes();
        $data['original_version'] = Guard::version($p->fresh());
        $data['unit'] = 'day';
        $this->put('/products/'.$p->id, $data)->assertSessionHasErrors('unit');
        $this->assertSame('hour', $q->items()->first()->unit);
    }

    public function test_expense_replay_revision_void_and_private_attachment_authorization(): void
    {
        $data = $this->expenseData();
        $file = UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\nSynthetic expense proof");
        $this->post('/expenses', array_merge($data, ['attachment' => $file]))->assertSessionHasNoErrors();
        $expense = Expense::first();
        $attachment = ExpenseAttachment::first();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get('/expenses/attachments/'.$attachment->id)->assertOk();
        $edit = $data;
        $edit['revision'] = 1;
        $edit['amount'] = '20.50';
        $this->put('/expenses/'.$expense->id, $edit)->assertSessionHasNoErrors();
        $this->put('/expenses/'.$expense->id, $edit)->assertSessionHasErrors('revision');
        $this->assertSame('20.50', $expense->fresh()->amount);
        $this->assertGreaterThanOrEqual(3, $expense->events()->count());
        $this->post('/expenses/'.$expense->id.'/void', ['revision' => 2, 'reason' => 'Duplicate bill corrected', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull($expense->fresh()->voided_at);
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($this->f['otherVendor']);
        $this->get('/expenses/attachments/'.$attachment->id)->assertForbidden();
    }

    public function test_expense_idempotency_invalid_upload_and_foreign_category_cannot_mutate_records(): void
    {
        $data = $this->expenseData();
        $this->post('/expenses', $data)->assertSessionHasNoErrors();
        $this->post('/expenses', $data)->assertSessionHasNoErrors();
        $this->assertSame(1, Expense::count());
        $data['amount'] = '99.00';
        $this->post('/expenses', $data)->assertSessionHasErrors('request_key');
        $bad = $this->expenseData();
        $this->post('/expenses', array_merge($bad, ['attachment' => UploadedFile::fake()->createWithContent('bad.svg', '<svg><script>alert(1)</script></svg>')]))->assertSessionHasErrors('attachment');
        $foreign = ExpenseCategory::create(['vendor_id' => 3, 'name' => 'Private category']);
        $bad['expense_category_id'] = $foreign->id;
        $this->post('/expenses', $bad)->assertSessionHasErrors('expense_category_id');
        $this->assertSame(1, Expense::count());
    }

    public function test_analytics_never_invents_missing_costs_and_reconciles_real_expense_totals(): void
    {
        $this->post('/expenses', $this->expenseData())->assertSessionHasNoErrors();
        $summary = app(Analytics::class)->summary(2, ['from_date' => today()->toDateString(), 'to_date' => today()->toDateString()]);
        $this->assertNull($summary['profit']);
        $this->assertSame(118000, (int) $summary['sales']->gross);
        $this->assertSame(1025, $summary['expenses']);
        $this->assertSame(0, $summary['collections']['net']);
        $this->get('/reports/analytics')->assertOk()->assertSee('Cost data incomplete');
        $this->get('/reports/analytics/export')->assertOk();
    }

    public function test_all_new_screens_and_quote_pdf_render_without_inline_app_code(): void
    {
        $p = $this->product();
        $q = $this->quote($p);
        $this->post('/expenses', $this->expenseData())->assertSessionHasNoErrors();
        $expense = Expense::first();
        foreach (['/quotations', '/quotations/create', '/quotations/'.$q->id, '/quotations/'.$q->id.'/edit', '/products', '/products/create', '/products/'.$p->id.'/edit', '/inventory', '/inventory/'.$p->id, '/expenses', '/expenses/create', '/expenses/'.$expense->id, '/expenses/'.$expense->id.'/edit', '/reports/analytics'] as $url) {
            $r = $this->get($url)->assertOk();
            $this->assertDoesNotMatchRegularExpression('/<style\b|<script(?![^>]*\bsrc=)/i', $r->getContent());
        }
        $r = $this->get('/quotations/'.$q->id.'/pdf')->assertOk();
        $this->assertStringStartsWith('%PDF-', $r->getContent());
    }
}
