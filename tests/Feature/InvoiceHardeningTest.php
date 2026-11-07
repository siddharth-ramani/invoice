<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Subscription;
use App\Services\Invoicing\InvoiceArchive;
use App\Services\Invoicing\InvoiceNumber;
use App\Services\Invoicing\InvoiceVersion;
use App\Services\Invoicing\InvoiceWriter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class InvoiceHardeningTest extends TestCase
{
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'logging.default' => 'null']);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $views = sys_get_temp_dir().'/bizpilot-m3-test-views';
        if (! is_dir($views)) {
            mkdir($views, 0700, true);
        }config(['view.compiled' => $views]);
        Storage::fake('local');
        BusinessFixture::createSchema();
        $this->f = BusinessFixture::seed();
        $this->actingAs($this->f['vendor']);
    }

    private function payload(string $number = 'GT/002'): array
    {
        return ['invoice_no' => $number, 'invoice_date' => today()->toDateString(), 'customer_id' => $this->f['customer']->id, 'discount_percent' => '10.00', 'notes' => 'Test note', 'items' => [['sr_no' => 1, 'item_name' => 'Test item', 'hsn_sac' => '5205', 'qty' => '100.00', 'rate' => '10.00', 'cgst_percent' => '9.00', 'sgst_percent' => '9.00']]];
    }

    private function editPayload(): array
    {
        $i = $this->f['invoice']->fresh('items');

        return array_merge($i->getAttributes(), ['items' => $i->items->toArray(), 'original_version' => app(InvoiceVersion::class)->token($i)]);
    }

    public function test_save_rechecks_expiry_missing_subscription_limit_and_role(): void
    {
        $this->f['subscription']->update(['end_date' => today()->subDay()]);
        $this->post('/invoices', $this->payload())->assertSessionHasErrors('subscription');
        $this->f['subscription']->update(['end_date' => today(), 'invoice_used' => 100]);
        $this->post('/invoices', $this->payload())->assertSessionHasErrors('subscription');
        $this->f['subscription']->update(['is_active' => 0]);
        $this->post('/invoices', $this->payload())->assertSessionHasErrors('subscription');
        $this->actingAs($this->f['customer'])->post('/invoices', $this->payload())->assertForbidden();
        $this->actingAs($this->f['admin'])->post('/invoices', $this->payload())->assertForbidden();
        $this->assertSame(1, Invoice::count());
    }

    public function test_end_date_is_inclusive_and_usage_is_atomic_and_replays_do_not_consume_it(): void
    {
        $this->f['subscription']->update(['end_date' => today(), 'invoice_used' => 99]);
        $this->post('/invoices', $this->payload())->assertRedirect(route('invoices.show', 2));
        $this->assertSame(100, $this->f['subscription']->fresh()->invoice_used);
        $this->post('/invoices', $this->payload())->assertRedirect(route('invoices.show', 2))->assertSessionHas('success', 'This invoice was already saved. Opened the existing invoice.');
        $this->assertSame(2, Invoice::count());
        $this->assertSame(100, $this->f['subscription']->fresh()->invoice_used);
        $changed = $this->payload();
        $changed['notes'] = 'Different draft';
        $this->from('/invoices/create')->post('/invoices', $changed)->assertSessionHasErrors('invoice_no')->assertSessionHasInput('notes', 'Different draft');
        $this->get('/invoices/create')->assertRedirect(route('subscription'));
    }

    public function test_conflicting_number_refresh_preserves_items_and_deleted_numbers_are_not_reused(): void
    {
        $this->post('/invoices', $this->payload())->assertRedirect();
        $different = $this->payload();
        $different['items'][0]['item_name'] = 'Unsaved draft item';
        $this->from('/invoices/create')->post('/invoices', $different)->assertSessionHasErrors('invoice_no');
        $this->get('/invoices/create')->assertOk()->assertSee('GT/003')->assertSee('Unsaved draft item');
        $i = Invoice::where('invoice_no', 'GT/002')->first();
        $i->delete();
        $this->assertSame('GT/003', app(InvoiceNumber::class)->next($this->f['vendor']));
        $copy = $this->f['invoice']->replicate();
        $copy->invoice_no = 'GT/090';
        $copy->save();
        $lower = $this->f['invoice']->replicate();
        $lower->invoice_no = 'GT/010';
        $lower->save();
        $this->assertSame('GT/091', app(InvoiceNumber::class)->next($this->f['vendor']));
    }

    public function test_failed_item_insert_rolls_back_invoice_items_and_usage(): void
    {
        InvoiceItem::creating(function ($item) {
            if ($item->item_name === 'Trigger failure') {
                throw new \RuntimeException('Synthetic failure');
            }
        });
        $p = $this->payload();
        $p['items'][] = array_merge($p['items'][0], ['item_name' => 'Trigger failure']);
        try {
            app(InvoiceWriter::class)->create($this->f['vendor'], $p);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic failure', $e->getMessage());
        }
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, InvoiceItem::count());
        $this->assertSame(1, $this->f['subscription']->fresh()->invoice_used);
    }

    public function test_metadata_edit_preserves_legacy_amounts_item_ids_and_encrypts_prior_copy(): void
    {
        $invoice = $this->f['invoice'];
        $invoice->update(['grand_total' => 1179, 'discount' => 1, 'discount_percent' => 0.15]);
        $before = $invoice->fresh()->getAttributes();
        $item = $invoice->items()->first();
        $p = $this->editPayload();
        $p['notes'] = 'Metadata only';
        $p['items'][0]['item_name'] = 'Updated description';
        $p['items'] = [7 => $p['items'][0]];
        $this->put('/invoices/'.$invoice->id, $p)->assertRedirect(route('invoices.show', $invoice));
        $saved = $invoice->fresh();
        foreach (['grand_total', 'discount', 'discount_percent', 'taxable_amount', 'cgst_total', 'sgst_total', 'total_tax', 'round_off', 'total_before_round'] as $key) {
            $this->assertSame($before[$key], $saved->getAttributes()[$key]);
        }
        $this->assertSame($item->id, $invoice->items()->first()->id);
        $files = Storage::disk('local')->allFiles('private/invoice-revisions');
        $this->assertCount(1, $files);
        $encrypted = Storage::disk('local')->get($files[0]);
        $this->assertStringNotContainsString('Metadata only', $encrypted);
        $snapshot = json_decode(Crypt::decryptString($encrypted), true);
        $this->assertSame(1179, (int) $snapshot['invoice']['grand_total']);
        $this->assertSame('Cotton yarn', $snapshot['items'][0]['item_name']);
    }

    public function test_financial_edit_requires_confirmation_and_stale_edits_are_rejected(): void
    {
        $p = $this->editPayload();
        $p['items'][0]['rate'] = '20.00';
        $this->put('/invoices/1', $p)->assertSessionHasErrors('confirm_recalculation');
        $this->assertSame(1180.0, (float) $this->f['invoice']->fresh()->grand_total);
        $p['confirm_recalculation'] = 1;
        $this->put('/invoices/1', $p)->assertRedirect(route('invoices.show', 1));
        $this->assertSame(2360.0, (float) $this->f['invoice']->fresh()->grand_total);
        $this->assertSame(1, $this->f['subscription']->fresh()->invoice_used);
        $p['notes'] = 'Stale tab';
        $this->put('/invoices/1', $p)->assertSessionHasErrors('original_version');
        $this->assertNotSame('Stale tab', $this->f['invoice']->fresh()->notes);
        $new = $this->editPayload();
        $new['invoice_no'] = 'OTHER/999';
        $this->put('/invoices/1', $new)->assertSessionHasErrors('invoice_no');
    }

    public function test_revision_failure_aborts_edit_and_delete_is_version_checked_and_soft_only(): void
    {
        $p = $this->editPayload();
        $p['notes'] = 'Must not save';
        $this->mock(InvoiceArchive::class)->shouldReceive('preserve')->once()->andThrow(new \RuntimeException('Storage unavailable'));
        try {
            app(InvoiceWriter::class)->update($this->f['vendor'], $this->f['invoice'], $p);
            $this->fail('Expected archive failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Storage unavailable', $e->getMessage());
        }
        $this->assertSame('Thank you for your business.', $this->f['invoice']->fresh()->notes);
        $this->delete('/invoices/1', ['original_version' => str_repeat('a', 64)])->assertSessionHasErrors('original_version');
    }

    public function test_lists_are_server_paginated_filtered_and_customer_history_is_scoped(): void
    {
        for ($i = 2; $i <= 25; $i++) {
            $row = $this->f['invoice']->replicate();
            $row->invoice_no = 'PAGE/'.$i;
            $row->save();
        }
        $this->get('/invoices')->assertOk()->assertViewHas('invoices', fn ($p) => $p->count() === 20 && $p->total() === 25)->assertSee('data-server-table', false);
        $this->get('/invoices?page=2')->assertOk()->assertViewHas('invoices', fn ($p) => $p->count() === 5);
        $this->get('/invoices?q=PAGE%2F25')->assertOk()->assertViewHas('invoices', fn ($p) => $p->total() === 1);
        $this->get('/customers/'.$this->f['customer']->id)->assertOk()->assertSee('Invoice history')->assertViewHas('invoices', fn ($p) => $p->total() === 25);
        $this->get('/customers/'.$this->f['foreignCustomer']->id)->assertForbidden();
        $this->get('/invoices?to_date='.today()->toDateString())->assertOk();
        $this->get('/invoices?sort=grand_total;DROP')->assertSessionHasErrors('sort');
    }

    public function test_foreign_invoice_all_paths_deny_and_customer_delete_cannot_break_history(): void
    {
        $this->actingAs($this->f['otherVendor']);
        foreach (['/invoices/1', '/invoices/1/edit', '/invoices/1/print', '/invoices/1/download'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->put('/invoices/1', $this->editPayload())->assertForbidden();
        $this->delete('/invoices/1')->assertForbidden();
        $this->actingAs($this->f['vendor'])->delete('/customers/'.$this->f['customer']->id)->assertSessionHas('error');
        $this->assertNotNull($this->f['customer']->fresh());
    }

    public function test_amount_overflows_extra_precision_and_fractional_discounts_are_rejected_without_writes(): void
    {
        $p = $this->payload();
        $p['items'][0]['rate'] = '10000000';
        $this->post('/invoices', $p)->assertSessionHasErrors('items.0.rate');
        $p = $this->payload();
        $p['items'][0]['rate'] = '1.005';
        $this->post('/invoices', $p)->assertSessionHasErrors('items.0.rate');
        $p = $this->payload();
        $p['discount_percent'] = '0.15';
        $this->post('/invoices', $p)->assertSessionHasErrors('discount_percent');
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, $this->f['subscription']->fresh()->invoice_used);
    }

    public function test_soft_delete_keeps_items_reserves_number_and_retains_encrypted_snapshot(): void
    {
        $version = app(InvoiceVersion::class)->token($this->f['invoice']->fresh('items'));
        $this->delete('/invoices/1', ['original_version' => $version])->assertRedirect(route('invoices.index'));
        $this->assertSame(0, Invoice::count());
        $this->assertSame(1, Invoice::withTrashed()->count());
        $this->assertSame(1, InvoiceItem::count());
        $this->assertCount(1, Storage::disk('local')->allFiles('private/invoice-revisions'));
        $this->assertSame('GT/002', app(InvoiceNumber::class)->next($this->f['vendor']));
        $this->assertSame(1, $this->f['subscription']->fresh()->invoice_used);
    }

    public function test_used_quota_does_not_block_edits_and_same_plan_activation_does_not_reset_it(): void
    {
        $this->f['subscription']->update(['invoice_used' => 100]);
        $this->post('/subscription/subscribe/'.$this->f['plan']->id)->assertRedirect(route('subscription'));
        $this->assertSame(100, $this->f['subscription']->fresh()->invoice_used);
        $this->assertSame(1, Subscription::count());
        $p = $this->editPayload();
        $p['notes'] = 'Allowed edit at limit';
        $this->put('/invoices/1', $p)->assertRedirect(route('invoices.show', 1));
        $this->assertSame('Allowed edit at limit', $this->f['invoice']->fresh()->notes);
    }

    public function test_server_ignores_submitted_totals_and_report_summaries_cover_all_pages(): void
    {
        $p = $this->payload();
        $p['grand_total'] = '0';
        $p['total_tax'] = '0';
        $p['discount'] = '9999';
        $this->post('/invoices', $p)->assertRedirect(route('invoices.show', 2));
        $this->assertSame(1080.0, (float) Invoice::find(2)->grand_total);
        for ($n = 3; $n <= 25; $n++) {
            $i = $this->f['invoice']->replicate();
            $i->invoice_no = 'SUM/'.$n;
            $i->save();
        }
        $this->get('/reports/sales?page=2')->assertOk()->assertViewHas('summary', fn ($s) => (int) $s->invoice_count === 25 && (float) $s->sales === 29400.0)
            ->assertViewHas('invoices', fn ($p) => $p->count() === 5);
        $this->get('/reports/gst?page=2')->assertOk()->assertViewHas('summary', fn ($s) => (float) $s->tax === 4500.0);
        $this->get('/customers?q=Shree')->assertOk()->assertViewHas('customers', fn ($p) => $p->total() === 1);
    }

    public function test_snapshot_storage_failure_returns_feedback_without_partial_save(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $p = $this->editPayload();
        $p['notes'] = 'Cannot persist this';
        $this->put('/invoices/1', $p)->assertSessionHasErrors('archive');
        $this->assertSame('Thank you for your business.', $this->f['invoice']->fresh()->notes);
    }

    public function test_literal_prefix_and_large_sequences_are_not_limited_by_integer_parsing(): void
    {
        $this->f['vendor']->forceFill(['invoice_prefix' => 'A_'])->save();
        foreach (['AB/999', 'A_/001', 'A_/99999999999999999999'] as $number) {
            $i = $this->f['invoice']->replicate();
            $i->invoice_no = $number;
            $i->save();
        }
        $this->assertSame('A_/100000000000000000000', app(InvoiceNumber::class)->next($this->f['vendor']));
    }

    public function test_metadata_edits_accept_legacy_quantity_without_rewriting_financial_snapshots(): void
    {
        $this->f['invoice']->items()->first()->update(['qty' => '150000.00']);
        $p = $this->editPayload();
        $p['notes'] = 'Keep legacy totals';
        $this->put('/invoices/1', $p)->assertRedirect(route('invoices.show', 1));
        $this->assertSame(1180.0, (float) $this->f['invoice']->fresh()->grand_total);
        $this->assertSame(150000.0, (float) $this->f['invoice']->items()->first()->qty);
    }
}
