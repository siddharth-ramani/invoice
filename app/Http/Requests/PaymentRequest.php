<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'customer')->where('created_by', $this->user()->id)],
            'idempotency_key' => ['required', 'uuid'],
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now(config('bizpilot.timezone'))->toDateString()],
            'amount' => ['required', 'numeric', 'regex:/^\d{1,10}(\.\d{1,2})?$/D', 'min:0.01', 'max:9999999999.99'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'upi', 'cheque', 'card', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['required', 'array', 'min:1', 'max:100'],
            'allocations.*.invoice_id' => ['required', 'integer', 'distinct'],
            'allocations.*.amount' => ['nullable', 'numeric', 'regex:/^\d{1,10}(\.\d{1,2})?$/D', 'min:0', 'max:9999999999.99'],
        ];
    }
}
