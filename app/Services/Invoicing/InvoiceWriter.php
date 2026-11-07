<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\InvoiceReceivable;
use App\Models\PaymentAllocation;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Business\Catalog;
use App\Services\Business\Modules;
use App\Services\Business\Platform;
use App\Services\Payments\PaymentModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceWriter
{
    public function __construct(private InvoiceCalculator $calculator, private InvoiceVersion $versions, private SubscriptionAccess $subscriptions, private InvoiceArchive $archive) {}

    private function vendor(User $actor): User
    {
        abort_unless($actor->role === 'vendor', 403);

        return User::whereKey($actor->id)->where('role', 'vendor')->lockForUpdate()->firstOrFail();
    }

    private function customer(User $vendor, array $data): void
    {
        if (! User::whereKey($data['customer_id'])->where('created_by', $vendor->id)->where('role', 'customer')->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['customer_id' => 'Choose a customer belonging to your business.']);
        }
    }

    public function create(User $actor, array $data): array
    {
        return DB::transaction(function () use ($actor, $data) {
            $vendor = $this->vendor($actor);
            $this->customer($vendor, $data);
            $matches = Invoice::withTrashed()->where('vendor_id', $vendor->id)->where('invoice_no', $data['invoice_no'])->lockForUpdate()->limit(2)->get();
            if ($matches->isNotEmpty()) {
                $existing = $matches->first();
                if ($matches->count() === 1 && ! $existing->trashed() && $this->versions->stored($existing) === $this->versions->canonical($data)) {
                    return ['invoice' => $existing, 'replayed' => true];
                }
                throw ValidationException::withMessages(['invoice_no' => 'This number is already used. Your draft is preserved; review the refreshed number and save again.']);
            }
            $subscription = $this->subscriptions->check($vendor, true, true);
            $calculated = $this->calculator->calculate($data);
            $invoice = Invoice::create(array_merge($calculated['totals'], [
                'vendor_id' => $vendor->id, 'customer_id' => $data['customer_id'], 'invoice_no' => $data['invoice_no'],
                'invoice_date' => $data['invoice_date'], 'notes' => $data['notes'] ?? null,
            ]));
            $invoice->items()->createMany($calculated['items']);
            app(Catalog::class)->capture($vendor, $invoice, $data);
            if (app(PaymentModule::class)->ready()) {
                InvoiceReceivable::create(['invoice_id' => $invoice->id, 'vendor_id' => $vendor->id, 'customer_id' => $invoice->customer_id, 'original_total' => $invoice->grand_total, 'legacy_status' => 'unpaid', 'origin' => 'new_invoice', 'confirmed_at' => now(), 'reviewed_by' => $vendor->id, 'admin_actor_id' => session('bizpilot.impersonation.admin_id'), 'review_note' => 'New invoice created with payment tracking enabled. No opening payment.']);
            }
            $subscription->increment('invoice_used');
            app(Platform::class)->audit('invoice.created', 'invoice', $invoice->id, [], $vendor->id);

            return ['invoice' => $invoice, 'replayed' => false];
        }, 5);
    }

    public function update(User $actor, Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($actor, $invoice, $data) {
            $vendor = $this->vendor($actor);
            $saved = Invoice::whereKey($invoice->id)->where('vendor_id', $vendor->id)->lockForUpdate()->firstOrFail();
            $saved->load('items');
            if (! hash_equals($this->versions->token($saved), $data['original_version'])) {
                throw ValidationException::withMessages(['original_version' => 'This invoice changed in another tab or session. Reload it and review the latest version before saving.']);
            }
            if ($data['invoice_no'] !== $saved->invoice_no) {
                throw ValidationException::withMessages(['invoice_no' => 'Saved invoice numbers cannot be changed.']);
            }
            $this->customer($vendor, $data);
            $this->subscriptions->check($vendor, false, true);
            $before = $this->versions->stored($saved);
            $after = $this->versions->canonical($data);
            if ($before === $after) {
                return $saved;
            }
            $financial = $this->versions->financial($before) !== $this->versions->financial($after);
            if ($financial && ! app(Modules::class)->ready('products') && app(Catalog::class)->links($saved)) {
                throw ValidationException::withMessages(['items' => 'Catalog-linked amounts cannot change while the catalog module is disabled. Notes and descriptions remain editable.']);
            }
            $receivable = app(PaymentModule::class)->installed() ? InvoiceReceivable::where('invoice_id', $saved->id)->lockForUpdate()->first() : null;
            $identityChange = $financial || $before['customer_id'] !== $after['customer_id'] || $before['invoice_date'] !== $after['invoice_date'];
            if ($receivable && $identityChange && ($receivable->origin !== 'new_invoice' || PaymentAllocation::where('invoice_id', $saved->id)->exists())) {
                throw ValidationException::withMessages(['items' => 'Payment tracking has started for this invoice. Its amounts, customer and invoice date are locked to preserve the ledger. Notes and descriptions can still be edited.']);
            }
            if ($receivable && $receivable->due_date && $after['invoice_date'] > $receivable->due_date) {
                throw ValidationException::withMessages(['invoice_date' => 'Invoice date cannot be later than its due date. Update the due date first.']);
            }
            if ($financial && ! filter_var($data['confirm_recalculation'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages(['confirm_recalculation' => 'Confirm that changing amounts will recalculate this invoice before saving.']);
            }
            $calculated = $financial ? $this->calculator->calculate($data) : null;
            $this->archive->preserve($saved, $financial ? 'financial-edit' : 'details-edit');
            $saved->fill(['customer_id' => $data['customer_id'], 'invoice_date' => $data['invoice_date'], 'notes' => $data['notes'] ?? null]);
            if ($financial) {
                app(Catalog::class)->release($saved, 'Invoice monetary edit: '.$saved->invoice_no);
                $saved->fill($calculated['totals']);
                $saved->items()->delete();
                $saved->items()->createMany($calculated['items']);
                app(Catalog::class)->capture($vendor, $saved, $data);
            } else {
                foreach ($saved->items->sortBy('sr_no')->values() as $index => $item) {
                    $item->update(['item_name' => $data['items'][$index]['item_name'], 'hsn_sac' => $data['items'][$index]['hsn_sac'] ?? null]);
                }
            }
            $saved->save();
            app(Platform::class)->audit('invoice.updated', 'invoice', $saved->id, ['financial' => $financial], $vendor->id);
            if ($receivable && $identityChange) {
                $receivable->update(['original_total' => $saved->grand_total, 'customer_id' => $saved->customer_id]);
            }

            return $saved->unsetRelation('items');
        }, 5);
    }

    public function delete(User $actor, Invoice $invoice, string $version): void
    {
        DB::transaction(function () use ($actor, $invoice, $version) {
            $vendor = $this->vendor($actor);
            $saved = Invoice::whereKey($invoice->id)->where('vendor_id', $vendor->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($this->versions->token($saved), $version)) {
                throw ValidationException::withMessages(['original_version' => 'This invoice changed after the list loaded. Refresh the list before deleting it.']);
            }
            $receivable = app(PaymentModule::class)->installed() ? InvoiceReceivable::where('invoice_id', $saved->id)->lockForUpdate()->first() : null;
            if ($receivable && ($receivable->origin !== 'new_invoice' || PaymentAllocation::where('invoice_id', $saved->id)->exists())) {
                throw ValidationException::withMessages(['invoice' => 'This invoice has payment tracking history and cannot be deleted. Retain its ledger and use payment reversals to correct receipts.']);
            }
            if (app(Modules::class)->installed('quotations') && Quotation::where('invoice_id', $saved->id)->exists()) {
                throw ValidationException::withMessages(['invoice' => 'This invoice was converted from a quotation and must remain available in its conversion history.']);
            }
            $this->archive->preserve($saved, 'soft-delete');
            app(Catalog::class)->release($saved, 'Invoice soft deletion: '.$saved->invoice_no, false);
            $saved->delete();
            app(Platform::class)->audit('invoice.soft_deleted', 'invoice', $saved->id, [], $vendor->id);
        }, 5);
    }
}
