<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\ListRequest;
use App\Http\Requests\Business\ProductRequest;
use App\Models\Product;
use App\Services\Business\Catalog;
use App\Services\Business\Guard;

class ProductController extends Controller
{
    public function __construct(private Catalog $catalog) {}

    public function index(ListRequest $request)
    {
        $products = Product::where('vendor_id', $request->user()->id)->when($request->validated('q'), fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$s.'%')->orWhere('sku', 'like', '%'.$s.'%')))->when($request->validated('kind'), fn ($q, $kind) => $q->where('kind', $kind))->when(in_array($request->validated('status'), ['active', 'archived'], true), fn ($q) => $q->where('is_active', $request->validated('status') === 'active'))->orderByDesc('id')->paginate(20)->withQueryString();

        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.form');
    }

    public function store(ProductRequest $request)
    {
        $saved = $this->catalog->save($request->user(), $request->validated());

        return redirect()->route('products.edit', $saved)->with('success', 'Catalog item created.');
    }

    public function edit(Product $product)
    {
        $this->authorize('update', $product);
        $originalVersion = Guard::version($product);

        return view('products.form', compact('product', 'originalVersion'));
    }

    public function update(ProductRequest $request, Product $product)
    {
        $saved = $this->catalog->save($request->user(), $request->validated(), $product);

        return redirect()->route('products.edit', $saved)->with('success', 'Catalog item updated.');
    }

    public function lookup(ListRequest $request)
    {
        $items = Product::where('vendor_id', $request->user()->id)->where('is_active', true)->when($request->validated('q'), fn ($q, $text) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$text.'%')->orWhere('sku', 'like', '%'.$text.'%')))->orderBy('name')->limit(50)->get(['id', 'name', 'sku', 'unit', 'hsn_sac', 'selling_price', 'cgst_percent', 'sgst_percent']);

        return response()->json(['items' => $items]);
    }
}
