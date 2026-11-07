<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('expense') ? $this->user()->can('update', $this->route('expense')) : $this->user()?->role === 'vendor';
    }

    public function rules(): array
    {
        $rules = ['expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('vendor_id', $this->user()->id)], 'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now(config('bizpilot.timezone'))->toDateString()], 'amount' => 'required|numeric|regex:/^\d{1,10}(\.\d{1,2})?$/D|min:0.01|max:9999999999.99', 'method' => ['required', Rule::in(['cash', 'bank_transfer', 'upi', 'cheque', 'card', 'other'])], 'reference' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:1000', 'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'];
        if ($this->route('expense')) {
            $rules['revision'] = 'required|integer|min:1';
        } else {
            $rules['request_key'] = 'required|uuid';
        }

        return $rules;
    }
}
