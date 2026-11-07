<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceReceivable;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReversal;
use App\Services\Invoicing\InvoiceVersion;
use App\Services\Payments\Money;
use App\Services\Payments\PaymentQueries;
use App\Services\Payments\PaymentWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class PaymentLedgerTest extends TestCase
{
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'logging.default' => 'null', 'bizpilot.payments_enabled' => true]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $views = sys_get_temp_dir().'/bizpilot-m4-test-views';
        if (! is_dir($views)) {
            mkdir($views, 0700, true);
        }config(['view.compiled' => $views]);
        Storage::fake('local');
        BusinessFixture::createSchema();
        $this->f = BusinessFixture::seed();
        (require database_path('migrations/2026_11_02_000001_create_payment_ledger_tables.php'))->up();
        $this->actingAs($this->f['vendor']);
    }

    private function review(string $action = 'confirm', ?Invoice $invoice = null): void
    {
        $i = $invoice ?? $this->f['invoice'];
        $this->post('/invoices/'.$i->id.'/payment-review', ['action' => $action, 'original_version' => app(InvoiceVersion::class)->token($i->fresh('items')), 'review_note' => 'Checked actual receipts with business records', 'acknowledge' => '1', 'due_date' => today()->addDays(10)->toDateString()])->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $i));
    }

    private function payload(string $amount = '500.00', array $lines = []): array
    {
        return ['idempotency_key' => Str::uuid()->toString(), 'customer_id' => $this->f['customer']->id, 'payment_date' => today()->toDateString(), 'amount' => $amount, 'method' => 'upi', 'reference' => 'BANK-123', 'notes' => 'Verified manually', 'allocations' => $lines ?: [['invoice_id' => $this->f['invoice']->id, 'amount' => $amount]]];
    }

    private function save(array $payload): Payment
    {
        $this->post('/payments', $payload)->assertSessionHasNoErrors();

        return Payment::latest('id')->firstOrFail();
    }

    public function test_module_is_disabled_without_setup_and_other_roles_cannot_use_it(): void
    {
        config(['bizpilot.payments_enabled' => false]);
        $this->get('/payments')->assertStatus(503);
        $this->get('/invoices/1')->assertOk()->assertDontSee('Record a payment');
        config(['bizpilot.payments_enabled' => true]);
        foreach (['admin', 'customer'] as $role) {
            $this->actingAs($this->f[$role]);
            $this->get('/payments')->assertForbidden();
            $this->post('/payments', [])->assertForbidden();
        }
    }

    public function test_legacy_status_never_creates_a_payment_or_balance_implicitly(): void
    {
        $this->f['invoice']->forceFill(['status' => 'paid'])->save();
        $this->assertSame(['outstanding' => 0, 'overdue' => 0, 'pending' => 1], app(PaymentQueries::class)->confirmedTotals($this->f['vendor']->id));
        $this->review('begin');
        $this->assertSame(0, Payment::count());
        $this->assertNull(InvoiceReceivable::first()->confirmed_at);
        $this->post('/invoices/1/payment-review', ['action' => 'confirm', 'original_version' => app(InvoiceVersion::class)->token($this->f['invoice']->fresh('items')), 'review_note' => 'Known paid invoice', 'acknowledge' => '1'])->assertSessionHasErrors('review');
        $this->save($this->payload('1180.00'));
        $this->review();
        $this->assertSame(0, app(PaymentQueries::class)->confirmedTotals($this->f['vendor']->id)['outstanding']);
        $this->assertSame('paid', $this->f['invoice']->fresh()->status);
    }

    public function test_partial_and_multiple_payments_reconcile_exact_paise_without_mutating_invoice(): void
    {
        $before = $this->f['invoice']->fresh()->getAttributes();
        $this->review();
        $this->save($this->payload('100.01'));
        $this->save($this->payload('1079.99'));
        $this->assertSame(118000, app(PaymentWriter::class)->paid(1));
        $this->assertSame(0, app(PaymentQueries::class)->confirmedTotals(2)['outstanding']);
        $this->assertSame($before, $this->f['invoice']->fresh()->getAttributes());
        $this->post('/payments', $this->payload('0.01'))->assertSessionHasErrors('allocations');
        $this->assertSame(2, Payment::count());
    }

    public function test_one_payment_allocates_to_two_invoices_and_idempotent_replay_is_exact(): void
    {
        $second = $this->f['invoice']->replicate();
        $second->invoice_no = 'GT/002';
        $second->save();
        $this->review();
        $this->review('confirm', $second);
        $p = $this->payload('1500.25', [['invoice_id' => 1, 'amount' => '1000.00'], ['invoice_id' => $second->id, 'amount' => '500.25']]);
        $saved = $this->save($p);
        $this->post('/payments', $p)->assertRedirect(route('payments.show', $saved));
        $this->assertSame(1, Payment::count());
        $this->assertSame(2, PaymentAllocation::count());
        $p['reference'] = 'different';
        $this->post('/payments', $p)->assertSessionHasErrors('idempotency_key');
        $this->assertSame(85975, app(PaymentQueries::class)->confirmedTotals(2)['outstanding']);
    }

    public function test_overallocation_duplicates_and_cross_customer_inputs_do_not_write(): void
    {
        $this->review();
        $this->post('/payments', $this->payload('1180.01'))->assertSessionHasErrors('allocations');
        $this->post('/payments', $this->payload('100', [['invoice_id' => 1, 'amount' => '50'], ['invoice_id' => 1, 'amount' => '50']]))->assertSessionHasErrors('allocations.0.invoice_id');
        $p = $this->payload();
        $p['customer_id'] = $this->f['foreignCustomer']->id;
        $this->post('/payments', $p)->assertSessionHasErrors('customer_id');
        $foreign = $this->f['invoice']->replicate();
        $foreign->vendor_id = $this->f['otherVendor']->id;
        $foreign->customer_id = $this->f['foreignCustomer']->id;
        $foreign->save();
        $this->post('/payments', $this->payload('500', [['invoice_id' => $foreign->id, 'amount' => '500']]))->assertSessionHasErrors('allocations');
        $this->assertSame(0, Payment::count());
    }

    public function test_invalid_amounts_dates_and_methods_preserve_draft_without_writes(): void
    {
        $this->review();
        foreach (['1.001', '-1', '0', '1e3', '10000000000.00'] as $amount) {
            $this->post('/payments', $this->payload($amount))->assertSessionHasErrors('amount');
        }
        $p = $this->payload();
        $p['payment_date'] = today()->addDay()->toDateString();
        $this->post('/payments', $p)->assertSessionHasErrors('payment_date');
        $p['payment_date'] = today()->subDay()->toDateString();
        $this->post('/payments', $p)->assertSessionHasErrors('payment_date');
        $p = $this->payload();
        $p['method'] = 'crypto';
        $this->post('/payments', $p)->assertSessionHasErrors('method');
        $this->assertSame(0, Payment::count());
    }

    public function test_allocation_failure_rolls_back_receipt_and_sequence(): void
    {
        $this->review();
        PaymentAllocation::creating(fn () => throw new \RuntimeException('Synthetic allocation failure'));
        try {
            app(PaymentWriter::class)->create($this->f['vendor'], $this->payload());
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic allocation failure', $e->getMessage());
        } finally {
            PaymentAllocation::flushEventListeners();
        }
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, PaymentAllocation::count());
        $p = $this->save($this->payload());
        $this->assertSame('RCPT/000001', $p->receipt_no);
    }

    public function test_reversal_retains_receipt_and_allocations_and_restores_balance_once(): void
    {
        $this->review();
        $p = $this->save($this->payload());
        $before = $p->getAttributes();
        $data = ['reversal_date' => today()->toDateString(), 'reason' => 'Incorrect reference recorded', 'confirm_reversal' => '1'];
        $this->post('/payments/'.$p->id.'/reverse', $data)->assertSessionHasNoErrors();
        $this->assertSame(118000, app(PaymentQueries::class)->confirmedTotals(2)['outstanding']);
        $this->assertSame($before, $p->fresh()->getAttributes());
        $this->assertSame(1, PaymentAllocation::count());
        $this->post('/payments/'.$p->id.'/reverse', $data)->assertSessionHasErrors('reversal');
        $this->assertSame(1, PaymentReversal::count());
        $this->assertSame(['gross' => 50000, 'reversed' => 50000, 'net' => 0], app(PaymentQueries::class)->collections(2, []));
        $this->get('/payments/'.$p->id)->assertOk()->assertSee('Reversed on');
        $this->delete('/payments/'.$p->id)->assertStatus(405);
    }

    public function test_tenant_denials_cover_receipt_reversal_ledger_and_exports(): void
    {
        $this->review();
        $p = $this->save($this->payload());
        $this->actingAs($this->f['otherVendor']);
        foreach (['/payments/'.$p->id, '/payments/'.$p->id.'/receipt', '/customers/4/ledger', '/customers/4/ledger/export'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/payments/'.$p->id.'/reverse', [])->assertForbidden();
        $this->post('/invoices/1/payment-review', [])->assertForbidden();
        $this->put('/invoices/1/due-date', [])->assertForbidden();
        $this->get('/payments')->assertOk()->assertDontSee('BANK-123');
        $this->get('/payments/export')->assertOk()->assertStreamedContent("Event,Date,Receipt,Customer,Method,Reference,\"Received INR\",\"Reversed INR\"\n");
    }

    public function test_ledger_date_filter_retains_opening_balance_and_reversal_event(): void
    {
        $this->f['invoice']->update(['invoice_date' => today()->subDays(10)->toDateString()]);
        $this->review();
        $p = $this->payload('500.25');
        $p['payment_date'] = today()->subDays(5)->toDateString();
        $saved = $this->save($p);
        $this->post('/payments/'.$saved->id.'/reverse', ['reversal_date' => today()->toDateString(), 'reason' => 'Wrong payment entry', 'confirm_reversal' => '1'])->assertSessionHasNoErrors();
        $rows = app(PaymentQueries::class)->ledger(2, 4, ['from_date' => today()->subDays(6)->toDateString()])->get();
        $this->assertCount(2, $rows);
        $this->assertSame(67975, (int) $rows[0]->balance);
        $this->assertSame(118000, (int) $rows[1]->balance);
        $this->get('/customers/4/ledger')->assertOk()->assertSee('Payment reversal');
        $this->get('/customers/4/ledger/export')->assertOk();
    }

    public function test_review_locks_financial_identity_even_if_module_is_later_disabled(): void
    {
        $this->review();
        $i = $this->f['invoice']->fresh('items');
        $p = array_merge($i->getAttributes(), ['items' => $i->items->toArray(), 'original_version' => app(InvoiceVersion::class)->token($i), 'confirm_recalculation' => 1]);
        $p['items'][0]['rate'] = '20';
        config(['bizpilot.payments_enabled' => false]);
        $this->put('/invoices/1', $p)->assertSessionHasErrors('items');
        $this->delete('/invoices/1', ['original_version' => $p['original_version']])->assertSessionHasErrors('invoice');
        $p['items'][0]['rate'] = '10';
        $p['notes'] = 'Allowed metadata correction';
        $this->put('/invoices/1', $p)->assertSessionHasNoErrors();
        $this->assertSame(1180, (int) $i->fresh()->grand_total);
    }

    public function test_new_invoices_enter_tracking_and_due_dates_are_explicit_and_version_checked(): void
    {
        $data = ['invoice_no' => 'GT/002', 'invoice_date' => today()->toDateString(), 'customer_id' => 4, 'items' => [['item_name' => 'New tracked item', 'qty' => '1', 'rate' => '100', 'cgst_percent' => '0', 'sgst_percent' => '0']]];
        $this->post('/invoices', $data)->assertSessionHasNoErrors();
        $row = InvoiceReceivable::firstOrFail();
        $this->assertNotNull($row->confirmed_at);
        $this->assertNull($row->due_date);
        $this->put('/invoices/'.$row->invoice_id.'/due-date', ['due_date' => today()->subDay()->toDateString()])->assertSessionHasErrors('due_date');
        $this->put('/invoices/'.$row->invoice_id.'/due-date', ['due_date' => today()->addDay()->toDateString()])->assertSessionHasNoErrors();
        $this->put('/invoices/'.$row->invoice_id.'/due-date', ['due_date' => today()->addDays(2)->toDateString()])->assertSessionHasErrors('due_date');
        $this->assertSame(10000, app(PaymentQueries::class)->confirmedTotals(2)['outstanding']);
        $this->assertSame(1, app(PaymentQueries::class)->confirmedTotals(2)['pending']);
    }

    public function test_receipt_uses_snapshots_and_pdf_and_payment_pages_render(): void
    {
        $this->review();
        $p = $this->save($this->payload('500.25'));
        $this->f['customer']->update(['name' => 'Renamed customer']);
        $this->get('/payments/'.$p->id)->assertOk()->assertSee('Shree Enterprise')->assertDontSee('Renamed customer');
        $response = $this->get('/payments/'.$p->id.'/receipt')->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        foreach (['/payments', '/payments/create?customer_id=4', '/receivables', '/receivables?balance=review', '/invoices/1', '/customers/4', '/customers/4/ledger'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_period_collections_include_reversals_of_older_receipts_and_safe_csv(): void
    {
        $this->f['invoice']->update(['invoice_date' => today()->subDays(10)->toDateString()]);
        $this->review();
        $p = $this->payload();
        $p['payment_date'] = today()->subDays(5)->toDateString();
        $p['reference'] = '=DANGEROUS()';
        $saved = $this->save($p);
        $this->post('/payments/'.$saved->id.'/reverse', ['reversal_date' => today()->toDateString(), 'reason' => 'Incorrect old receipt', 'confirm_reversal' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(['gross' => 0, 'reversed' => 50000, 'net' => -50000], app(PaymentQueries::class)->collections(2, ['from_date' => today()->toDateString(), 'to_date' => today()->toDateString()]));
        $response = $this->get('/payments/export?from_date='.today()->toDateString());
        $response->assertOk();
        $this->assertStringContainsString("'=DANGEROUS()", $response->streamedContent());
        $this->assertSame(12345, Money::cents('123.45'));
    }

    public function test_overdue_requires_review_and_an_explicit_past_due_date(): void
    {
        $this->f['invoice']->update(['invoice_date' => today()->subDays(10)->toDateString()]);
        $this->review();
        $row = InvoiceReceivable::first();
        $row->update(['due_date' => today()->subDay()->toDateString()]);
        $this->assertSame(118000, app(PaymentQueries::class)->confirmedTotals(2)['overdue']);
        $this->get('/receivables?balance=overdue')->assertOk()->assertSee('GT/001');
        $row->update(['due_date' => today()->toDateString()]);
        $this->assertSame(0, app(PaymentQueries::class)->confirmedTotals(2)['overdue']);
        $row->update(['due_date' => null]);
        $this->assertSame(0, app(PaymentQueries::class)->confirmedTotals(2)['overdue']);
    }

    public function test_collection_summaries_cover_all_pages_and_expiry_does_not_block_receipts(): void
    {
        $this->review();
        $this->f['subscription']->update(['end_date' => today()->subDay()->toDateString()]);
        for ($i = 0; $i < 25; $i++) {
            $this->save($this->payload('1.01'));
        }
        $response = $this->get('/payments')->assertOk();
        $this->assertSame(20, $response->viewData('payments')->count());
        $this->assertSame(25, $response->viewData('payments')->total());
        $this->assertSame(2525, $response->viewData('summary')['gross']);
        $this->get('/payments?page=2')->assertOk()->assertViewHas('payments', fn ($p) => $p->count() === 5);
    }

    public function test_stale_or_cancelled_invoice_review_cannot_establish_a_balance(): void
    {
        $data = ['action' => 'confirm', 'original_version' => str_repeat('0', 64), 'review_note' => 'Review evidence note', 'acknowledge' => '1'];
        $this->post('/invoices/1/payment-review', $data)->assertSessionHasErrors('original_version');
        $this->f['invoice']->forceFill(['status' => 'cancelled'])->save();
        $data['original_version'] = app(InvoiceVersion::class)->token($this->f['invoice']->fresh('items'));
        $this->post('/invoices/1/payment-review', $data)->assertSessionHasErrors('review');
        $this->assertSame(0, InvoiceReceivable::count());
        $this->assertSame(['outstanding' => 0, 'overdue' => 0, 'pending' => 0], app(PaymentQueries::class)->confirmedTotals(2));
    }

    public function test_payment_dashboard_reads_real_sources_and_does_not_modify_invoice_totals(): void
    {
        $this->review();
        $this->save($this->payload('500.25'));
        $response = $this->get('/dashboard')->assertOk();
        $this->assertSame(50025, $response->viewData('collections')['net']);
        $this->assertSame(67975, $response->viewData('receivables')['outstanding']);
        $this->assertCount(1, $response->viewData('recentPayments'));
        $response->assertSee('Net collections')->assertSee('Recent payments');
        $this->assertSame(1180, (int) $this->f['invoice']->fresh()->grand_total);
    }

    public function test_draft_allocations_remain_bound_to_invoice_ids_when_an_invoice_disappears(): void
    {
        $this->review();
        $second = $this->f['invoice']->replicate();
        $second->invoice_no = 'GT/002';
        $second->save();
        $this->review('confirm', $second);
        $this->save($this->payload('1180.00'));
        $response = $this->withSession(['_old_input' => ['customer_id' => 4, 'allocations' => [['invoice_id' => 1, 'amount' => '500.00'], ['invoice_id' => $second->id, 'amount' => '100.00']]]])->get('/payments/create?customer_id=4')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $fields = $xpath->query('//input[@name="allocations[0][amount]"]');
        $this->assertSame('100.00', $fields->item(0)->getAttribute('value'));
        $ids = $xpath->query('//input[@name="allocations[0][invoice_id]"]');
        $this->assertSame((string) $second->id, $ids->item(0)->getAttribute('value'));
    }

    public function test_financial_models_and_migration_rollback_cannot_erase_history(): void
    {
        $this->review();
        $p = $this->save($this->payload());
        $this->post('/payments/'.$p->id.'/reverse', ['reversal_date' => today()->toDateString(), 'reason' => 'Retained reversal evidence', 'confirm_reversal' => '1'])->assertSessionHasNoErrors();
        foreach ([$p, $p->allocations()->first(), $p->reversal()->first()] as $record) {
            try {
                $record->delete();
                $this->fail('Expected immutable record');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('retained', $e->getMessage());
            }
            try {
                $record->forceFill(['amount' => '1.00'])->save();
                $this->fail('Expected immutable record');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('immutable', $e->getMessage());
            }
        }
        try {
            (require database_path('migrations/2026_11_02_000001_create_payment_ledger_tables.php'))->down();
            $this->fail('Expected retained schema');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('disabled', $e->getMessage());
        }
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, PaymentReversal::count());
        $this->assertSame(1, PaymentAllocation::count());
    }

    public function test_new_uncollected_invoices_keep_edit_and_soft_delete_workflows(): void
    {
        $data = ['invoice_no' => 'GT/002', 'invoice_date' => today()->toDateString(), 'customer_id' => 4, 'items' => [['item_name' => 'Editable new invoice', 'qty' => '1', 'rate' => '100', 'cgst_percent' => '0', 'sgst_percent' => '0']]];
        $this->post('/invoices', $data)->assertSessionHasNoErrors();
        $invoice = Invoice::latest('id')->first()->load('items');
        $edit = array_merge($invoice->getAttributes(), ['items' => $invoice->items->toArray(), 'original_version' => app(InvoiceVersion::class)->token($invoice), 'confirm_recalculation' => 1]);
        $edit['items'][0]['rate'] = '200';
        $this->put('/invoices/'.$invoice->id, $edit)->assertSessionHasNoErrors();
        $this->assertSame('200.00', InvoiceReceivable::where('invoice_id', $invoice->id)->first()->original_total);
        $version = app(InvoiceVersion::class)->token($invoice->fresh('items'));
        $this->delete('/invoices/'.$invoice->id, ['original_version' => $version])->assertSessionHasNoErrors();
        $this->assertTrue($invoice->fresh()->trashed());
        $this->assertSame(0, app(PaymentQueries::class)->confirmedTotals(2)['outstanding']);
        $this->assertSame(0, app(PaymentQueries::class)->ledger(2, 4)->count());
        $this->assertSame(1, InvoiceReceivable::count());
    }

    public function test_new_invoice_identity_stays_locked_even_after_its_receipt_is_reversed(): void
    {
        $data = ['invoice_no' => 'GT/002', 'invoice_date' => today()->toDateString(), 'customer_id' => 4, 'items' => [['item_name' => 'New collected invoice', 'qty' => '1', 'rate' => '100', 'cgst_percent' => '0', 'sgst_percent' => '0']]];
        $this->post('/invoices', $data)->assertSessionHasNoErrors();
        $invoice = Invoice::latest('id')->first()->load('items');
        $payment = $this->save($this->payload('1.00', [['invoice_id' => $invoice->id, 'amount' => '1.00']]));
        $this->post('/payments/'.$payment->id.'/reverse', ['reversal_date' => today()->toDateString(), 'reason' => 'Reverse collected entry', 'confirm_reversal' => '1'])->assertSessionHasNoErrors();
        $edit = array_merge($invoice->getAttributes(), ['items' => $invoice->items->toArray(), 'original_version' => app(InvoiceVersion::class)->token($invoice), 'confirm_recalculation' => 1]);
        $edit['items'][0]['rate'] = '200';
        $this->put('/invoices/'.$invoice->id, $edit)->assertSessionHasErrors('items');
        $this->delete('/invoices/'.$invoice->id, ['original_version' => $edit['original_version']])->assertSessionHasErrors('invoice');
        $this->assertFalse($invoice->fresh()->trashed());
        $this->assertSame('100.00', InvoiceReceivable::where('invoice_id', $invoice->id)->first()->original_total);
    }
}
