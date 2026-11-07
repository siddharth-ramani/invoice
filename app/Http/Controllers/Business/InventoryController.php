<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\ListRequest;
use App\Models\InventorySetting;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Business\Catalog;
use App\Services\Business\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(ListRequest $request)
    {
        $enabled = InventorySetting::where('vendor_id', $request->user()->id)->value('enabled') ?? false;
        $products = Product::where('vendor_id', $request->user()->id)->where('kind', 'product')->where('track_stock', true)->when($request->validated('q'), fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))->select('products.*')->selectSub(StockMovement::selectRaw('COALESCE(SUM(quantity),0)')->whereColumn('product_id', 'products.id'), 'stock')->orderBy('name')->paginate(20)->withQueryString();

        return view('inventory.index', compact('enabled', 'products'));
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean', 'confirm' => 'accepted']);
        DB::transaction(function () use ($request, $data) {
            Guard::vendor($request->user());
            if (! $data['enabled'] && (int) StockMovement::where('vendor_id', $request->user()->id)->sum(DB::raw('ROUND(quantity*100,0)')) !== 0) {
                Guard::fail('enabled', 'Reconcile every tracked product to zero before disabling inventory.');
            }InventorySetting::updateOrCreate(['vendor_id' => $request->user()->id], ['enabled' => $data['enabled']]);
        }, 5);

        return back()->with('success', 'Inventory settings saved.');
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);
        $movements = StockMovement::where('vendor_id', auth()->id())->where('product_id', $product->id)->orderByDesc('id')->paginate(20);
        $stock = app(Catalog::class)->stock($product->id);

        return view('inventory.show', compact('product', 'movements', 'stock'));
    }

    public function adjust(Request $request, Product $product)
    {
        $this->authorize('update', $product);
        $data = $request->validate(['direction' => 'required|in:in,out', 'quantity' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/D|min:0.01|max:99999999.99', 'reason' => 'required|string|min:5|max:1000', 'request_key' => 'required|uuid']);
        app(Catalog::class)->adjust($request->user(), $product, $data);

        return back()->with('success', 'Stock movement recorded. History is retained.');
    }
}
