<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Services\Business\Modules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('items'))) {
            $this->merge(['items' => array_values($this->input('items'))]);
        }
    }

    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice ? $this->user()->can('update', $invoice) : $this->user()->can('create', Invoice::class);
    }

    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $legacy = $invoice instanceof Invoice;
        $rules = [
            'invoice_no' => ['required', 'string', 'max:50', 'not_regex:/[\x00-\x1F\x7F\\\\]/u'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('created_by', $this->user()->id)->where('role', 'customer')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:20'],
            'items.*.qty' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/', $legacy ? 'min:0' : 'min:0.01', $legacy ? 'max:99999999.99' : 'max:100000'],
            'items.*.rate' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/', 'min:0', $legacy ? 'max:99999999.99' : 'max:10000000'],
            'items.*.cgst_percent' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/', 'min:0', $legacy ? 'max:999.99' : 'max:28'],
            'items.*.sgst_percent' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/', 'min:0', $legacy ? 'max:999.99' : 'max:28'],
            'discount_percent' => ['nullable', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/', 'min:0', $legacy ? 'max:999.99' : 'max:100'],
            'confirm_recalculation' => ['sometimes', 'boolean'],
        ];
        if ($invoice instanceof Invoice) {
            $rules['invoice_no'][] = Rule::in([$invoice->invoice_no]);
            $rules['original_version'] = ['required', 'string', 'size:64'];
        }
        if (app(Modules::class)->installed('products')) {
            $rules['items.*.product_id'] = ['nullable', 'integer', Rule::exists('products', 'id')->where('vendor_id', $this->user()->id)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['invoice_no.in' => 'Saved invoice numbers cannot be changed.', 'original_version.required' => 'Reload this invoice before saving so newer changes are not overwritten.', 'items.max' => 'Use at most 200 items per invoice.'];
    }
}
