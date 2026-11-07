<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PaymentFilterRequest extends ReportFilterRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'vendor';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), ['q' => ['nullable', 'string', 'max:100'], 'customer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'customer')->where('created_by', $this->user()->id)], 'method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'upi', 'cheque', 'card', 'other'])], 'balance' => ['nullable', Rule::in(['outstanding', 'overdue', 'review'])]]);
    }
}
