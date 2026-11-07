<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('product') ? $this->user()->can('update', $this->route('product')) : $this->user()?->role === 'vendor';
    }

    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/D', 'min:0', 'max:99999999.99'];

        return ['name' => 'required|string|max:255', 'sku' => ['nullable', 'string', 'max:80', Rule::unique('products')->where('vendor_id', $this->user()->id)->ignore($this->route('product')?->id)], 'kind' => ['required', Rule::in(['product', 'service'])], 'unit' => 'required|string|max:20', 'hsn_sac' => 'nullable|string|max:20', 'selling_price' => array_replace($money, [0 => 'required']), 'purchase_price' => $money, 'cgst_percent' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/D|min:0|max:28', 'sgst_percent' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/D|min:0|max:28', 'is_active' => 'required|boolean', 'track_stock' => 'required|boolean', 'low_stock_level' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/D|min:0|max:99999999.99', 'original_version' => $this->route('product') ? 'required|string|size:64' : 'nullable'];
    }
}
