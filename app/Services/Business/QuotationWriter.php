<?php

namespace App\Services\Business;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Invoicing\InvoiceNumber;
use App\Services\Invoicing\InvoiceWriter;
use Illuminate\Support\Facades\DB;

class QuotationWriter
{
    public function save(User $actor, array $data, ?Quotation $quotation = null): Quotation
    {
        return DB::transaction(function () use ($actor, $data, $quotation) {
            $vendor = Guard::vendor($actor);
            $customer = User::whereKey($data['customer_id'])->where('created_by', $vendor->id)->where('role', 'customer')->lockForUpdate()->first();
            if (! $customer) {
                Guard::fail('customer_id', 'Choose a customer belonging to your business.');
            }
            $saved = $quotation ? Quotation::whereKey($quotation->id)->where('vendor_id', $vendor->id)->lockForUpdate()->firstOrFail() : null;
            if ($saved) {
                if ($saved->status !== 'draft') {
                    Guard::fail('quotation', 'Only draft quotations can be edited. Duplicate it to make a revised proposal.');
                }if ((int) $data['revision'] !== $saved->revision) {
                    Guard::fail('revision', 'Quotation changed in another tab. Reload before saving.');
                }$this->event($saved, 'before-edit');
            }
            $payload = $data;
            $payload['invoice_date'] = $data['quotation_date'];
            $calculated = app(InvoiceCalculator::class)->calculate($payload);
            $lines = [];
            foreach (array_values($calculated['items']) as $i => $line) {
                $productId = $data['items'][$i]['product_id'] ?? null;
                $product = $productId ? Product::whereKey($productId)->where('vendor_id', $vendor->id)->where('is_active', true)->lockForUpdate()->first() : null;
                if ($productId && ! $product) {
                    Guard::fail('items', 'A selected catalog item is unavailable.');
                }$lines[] = array_merge($line, ['product_id' => $product?->id, 'unit' => $product?->unit ?? 'kg']);
            }
            $fields = ['customer_id' => $customer->id, 'quotation_date' => $data['quotation_date'], 'valid_until' => $data['valid_until'], 'notes' => $data['notes'] ?? null, 'totals' => $calculated['totals'], 'party_snapshot' => ['business' => ['name' => $vendor->company_name ?: $vendor->name, 'address' => $vendor->address, 'gst_number' => $vendor->gst_number, 'mobile' => $vendor->mobile], 'customer' => ['name' => $customer->name, 'address' => $customer->address, 'gst_number' => $customer->gst_number, 'mobile' => $customer->mobile]]];
            if ($saved) {
                $saved->update(array_merge($fields, ['revision' => $saved->revision + 1]));
                $saved->items()->delete();
            } else {
                $sequence = (int) Quotation::where('vendor_id', $vendor->id)->max('sequence') + 1;
                $saved = Quotation::create(array_merge($fields, ['vendor_id' => $vendor->id, 'sequence' => $sequence, 'quotation_no' => 'QT/'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT), 'status' => 'draft']));
            }
            $saved->items()->createMany($lines);
            $this->event($saved, $quotation ? 'edited' : 'created');

            return $saved->fresh();
        }, 5);
    }

    public function status(User $actor, Quotation $quotation, string $status, int $revision, string $reason): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $status, $revision, $reason) {
            Guard::vendor($actor);
            $saved = Quotation::whereKey($quotation->id)->where('vendor_id', $actor->id)->lockForUpdate()->firstOrFail();
            if ($revision !== $saved->revision) {
                Guard::fail('revision', 'Quotation changed. Reload it.');
            }$allowed = ['draft' => ['sent', 'rejected', 'expired'], 'sent' => ['accepted', 'rejected', 'expired'], 'accepted' => ['rejected', 'expired'], 'rejected' => [], 'expired' => [], 'converted' => []];
            if (! in_array($status, $allowed[$saved->status] ?? [], true)) {
                Guard::fail('status', 'This status transition is not allowed.');
            }$today = now(config('bizpilot.timezone'))->toDateString();
            if (in_array($status, ['sent', 'accepted'], true) && $saved->valid_until < $today) {
                Guard::fail('status', 'This quotation has expired. Duplicate it to prepare a new offer.');
            }if ($status === 'expired' && $saved->valid_until >= $today) {
                Guard::fail('status', 'This quotation has not expired yet.');
            }$saved->update(['status' => $status, 'revision' => $saved->revision + 1]);
            $this->event($saved, $status, $reason);

            return $saved;
        }, 5);
    }

    public function duplicate(User $actor, Quotation $quotation): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation) {
            Guard::vendor($actor);
            $source = Quotation::whereKey($quotation->id)->where('vendor_id', $actor->id)->lockForUpdate()->firstOrFail();
            $data = ['customer_id' => $source->customer_id, 'quotation_date' => now(config('bizpilot.timezone'))->toDateString(), 'valid_until' => now(config('bizpilot.timezone'))->addDays(30)->toDateString(), 'notes' => $source->notes, 'discount_percent' => $source->totals['discount_percent'], 'items' => $source->items->toArray()];
            $copy = $this->save($actor, $data);
            $this->event($copy, 'duplicated', 'Duplicated from '.$source->quotation_no);

            return $copy;
        }, 5);
    }

    public function convert(User $actor, Quotation $quotation, int $revision): Invoice
    {
        return DB::transaction(function () use ($actor, $quotation, $revision) {
            $vendor = Guard::vendor($actor);
            $saved = Quotation::whereKey($quotation->id)->where('vendor_id', $actor->id)->lockForUpdate()->firstOrFail();
            if ($saved->invoice_id) {
                return $saved->invoice()->firstOrFail();
            }if ($saved->revision !== $revision) {
                Guard::fail('revision', 'Quotation changed. Reload before converting.');
            }if ($saved->status !== 'accepted' || $saved->valid_until < now(config('bizpilot.timezone'))->toDateString()) {
                Guard::fail('quotation', 'Only an accepted, unexpired quotation can be converted.');
            }$data = ['invoice_no' => app(InvoiceNumber::class)->next($vendor), 'invoice_date' => now(config('bizpilot.timezone'))->toDateString(), 'customer_id' => $saved->customer_id, 'discount_percent' => $saved->totals['discount_percent'], 'notes' => $saved->notes, 'items' => $saved->items->toArray()];
            $result = app(InvoiceWriter::class)->create($vendor, $data);
            $saved->update(['invoice_id' => $result['invoice']->id, 'status' => 'converted', 'revision' => $saved->revision + 1]);
            $this->event($saved, 'converted', 'Converted into '.$result['invoice']->invoice_no);

            return $result['invoice'];
        }, 5);
    }

    private function event(Quotation $q, string $action, ?string $reason = null): void
    {
        $q->unsetRelation('items');
        $q->events()->create(['action' => $action, 'actor_id' => $q->vendor_id, 'admin_actor_id' => Guard::admin(), 'snapshot' => ['quotation' => $q->getAttributes(), 'items' => $q->items->toArray()], 'reason' => $reason]);
        app(Platform::class)->audit('quotation.'.$action, 'quotation', $q->id, [], $q->vendor_id);
    }
}
