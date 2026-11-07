<?php

namespace App\Services\Business;

use App\Models\InventorySetting;
use App\Models\Invoice;
use App\Models\InvoiceProductLink;
use App\Models\Product;
use App\Models\QuotationItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Payments\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Catalog
{
    public function save(User $actor, array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($actor, $data, $product) {
            Guard::vendor($actor);
            $saved = $product ? Product::whereKey($product->id)->where('vendor_id', $actor->id)->lockForUpdate()->firstOrFail() : null;
            if (! empty($data['sku']) && Product::where('vendor_id', $actor->id)->where('sku', $data['sku'])->when($saved, fn ($q) => $q->whereKeyNot($saved->id))->exists()) {
                Guard::fail('sku', 'This SKU is already used by your business.');
            }
            if ($saved) {
                Guard::assertVersion($saved, $data['original_version']);
                if (($saved->unit !== $data['unit'] || $saved->kind !== $data['kind']) && (StockMovement::where('product_id', $saved->id)->exists() || InvoiceProductLink::where('product_id', $saved->id)->exists() || QuotationItem::where('product_id', $saved->id)->exists())) {
                    Guard::fail('unit', 'Unit and item type are locked after inventory or invoice use. Create a new catalog item for a different unit.');
                }if ($saved->track_stock && ! $data['track_stock'] && $this->stock($saved->id) !== 0) {
                    Guard::fail('track_stock', 'Reconcile remaining stock to zero before stopping stock tracking.');
                }
            }
            unset($data['original_version']);
            if ($data['kind'] === 'service') {
                $data['track_stock'] = false;
            }
            if ($saved) {
                $saved->update($data);

                return $saved;
            }

            return Product::create(array_merge($data, ['vendor_id' => $actor->id]));
        }, 5);
    }

    public function stock(int $productId): int
    {
        return (int) StockMovement::where('product_id', $productId)->sum(DB::raw('ROUND(quantity * 100,0)'));
    }

    public function adjust(User $actor, Product $product, array $data): StockMovement
    {
        return DB::transaction(function () use ($actor, $product, $data) {
            Guard::vendor($actor);
            $saved = Product::whereKey($product->id)->where('vendor_id', $actor->id)->lockForUpdate()->firstOrFail();
            if (! $saved->track_stock || $saved->kind !== 'product') {
                Guard::fail('product', 'Enable stock tracking for a physical product first.');
            }if (! InventorySetting::where('vendor_id', $actor->id)->where('enabled', true)->exists()) {
                Guard::fail('inventory', 'Enable inventory before recording stock.');
            }
            $units = InvoiceCalculator::units($data['quantity']);
            $delta = $data['direction'] === 'out' ? -$units : $units;
            $hash = hash('sha256', json_encode([$saved->id, $delta, $data['reason']], JSON_THROW_ON_ERROR));
            $existing = StockMovement::where('vendor_id', $actor->id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    Guard::fail('request_key', 'This submission key belongs to another stock adjustment.');
                }

                return $existing;
            }
            if ($units <= 0 || abs($delta) > 999999999999) {
                Guard::fail('quantity', 'Enter a positive supported stock quantity.');
            }if ($this->stock($saved->id) + $delta < 0) {
                Guard::fail('quantity', 'This adjustment would make stock negative.');
            }

            return $this->movement($actor->id, $saved, $delta, 'manual', $data['reason'], $data['request_key'], $hash);
        }, 5);
    }

    public function capture(User $vendor, Invoice $invoice, array $data): void
    {
        if (collect($data['items'])->contains(fn ($item) => ! empty($item['product_id'])) && ! app(Platform::class)->allowance($vendor->id, 'catalog')['enabled']) {
            Guard::fail('items', 'Catalog invoice entry is disabled for this plan.');
        }
        if (! app(Modules::class)->ready('products')) {
            if (collect($data['items'])->contains(fn ($item) => ! empty($item['product_id']))) {
                Guard::fail('items', 'Catalog entry is temporarily disabled. Reload or use a manual item.');
            }

            return;
        }
        $enabled = InventorySetting::where('vendor_id', $vendor->id)->where('enabled', true)->exists();
        $items = $invoice->items()->orderBy('sr_no')->get();
        foreach (array_values($data['items']) as $i => $input) {
            $id = $input['product_id'] ?? null;
            if (! $id) {
                continue;
            }$product = Product::whereKey($id)->where('vendor_id', $vendor->id)->where('is_active', true)->lockForUpdate()->first();
            if (! $product) {
                Guard::fail('items', 'A selected catalog item is not active or does not belong to your business.');
            }$line = $items[$i];
            InvoiceProductLink::create(['invoice_id' => $invoice->id, 'invoice_item_id' => $line->id, 'product_id' => $product->id, 'unit' => $product->unit, 'quantity' => $line->qty, 'cost_rate' => $product->purchase_price]);
            if ($enabled && $product->track_stock && $product->kind === 'product') {
                $quantity = InvoiceCalculator::units($line->qty);
                if ($this->stock($product->id) < $quantity) {
                    Guard::fail('items', 'Insufficient stock for '.$product->name.'. Adjust stock or reduce quantity.');
                }$this->movement($vendor->id, $product, -$quantity, 'invoice_sale', 'Invoice '.$invoice->invoice_no, Str::uuid()->toString(), hash('sha256', 'invoice:'.$invoice->id.':'.$line->id), $invoice->id);
            }
        }
    }

    public function release(Invoice $invoice, string $reason, bool $detach = true): void
    {
        if (! app(Modules::class)->installed('products')) {
            return;
        }
        $movements = StockMovement::where('vendor_id', $invoice->vendor_id)->where('invoice_id', $invoice->id)->where('kind', 'invoice_sale')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_movements as undo')->whereColumn('undo.reverses_id', 'stock_movements.id'))->lockForUpdate()->get();
        foreach ($movements as $movement) {
            $product = Product::whereKey($movement->product_id)->where('vendor_id', $invoice->vendor_id)->lockForUpdate()->firstOrFail();
            $units = InvoiceCalculator::units(ltrim($movement->quantity, '-'));
            $this->movement($invoice->vendor_id, $product, $units, 'invoice_reversal', $reason, Str::uuid()->toString(), hash('sha256', 'reversal:'.$movement->id), $invoice->id, $movement->id);
        }
        if ($detach) {
            InvoiceProductLink::where('invoice_id', $invoice->id)->delete();
        }
    }

    public function links(Invoice $invoice): array
    {
        return app(Modules::class)->installed('products') ? InvoiceProductLink::where('invoice_id', $invoice->id)->orderBy('invoice_item_id')->pluck('product_id', 'invoice_item_id')->map(fn ($id) => (int) $id)->all() : [];
    }

    private function movement(int $vendorId, Product $product, int $delta, string $kind, string $reason, string $key, string $hash, ?int $invoiceId = null, ?int $reverses = null): StockMovement
    {
        $movement = StockMovement::create(['vendor_id' => $vendorId, 'product_id' => $product->id, 'invoice_id' => $invoiceId, 'reverses_id' => $reverses, 'quantity' => Money::decimal($delta), 'kind' => $kind, 'unit' => $product->unit, 'reason' => $reason, 'request_key' => $key, 'request_hash' => $hash, 'admin_actor_id' => Guard::admin()]);
        app(Platform::class)->audit('stock.'.$kind, 'stock_movement', $movement->id, ['product_id' => $product->id], $vendorId);
        $stock = $this->stock($product->id);
        $threshold = InvoiceCalculator::units($product->low_stock_level);
        if ($delta < 0 && $stock <= $threshold && $stock - $delta > $threshold) {
            app(Notifications::class)->add($vendorId, 'stock:'.$movement->id, 'Low stock', 'The catalog item :name reached its configured low stock level.', route('inventory.show', $product, false), ['name' => $product->name]);
        }

        return $movement;
    }
}
