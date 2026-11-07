<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\InvoiceRequest;
use Illuminate\Validation\Rule;

class QuotationRequest extends InvoiceRequest
{
    public function authorize(): bool
    {
        return $this->route('quotation') ? $this->user()->can('update', $this->route('quotation')) : $this->user()?->role === 'vendor';
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['invoice_no'],$rules['invoice_date'],$rules['confirm_recalculation']);
        $rules['quotation_date'] = ['required', 'date_format:Y-m-d'];
        $rules['valid_until'] = ['required', 'date_format:Y-m-d', 'after_or_equal:quotation_date'];
        $rules['items.*.product_id'] = ['nullable', 'integer', Rule::exists('products', 'id')->where('vendor_id', $this->user()->id)->where('is_active', true)];
        if ($this->route('quotation')) {
            $rules['revision'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }
}
