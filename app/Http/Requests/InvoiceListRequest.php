<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Validation\Rule;

class InvoiceListRequest extends ReportFilterRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Invoice::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['unpaid', 'paid', 'cancelled'])], 'sort' => ['nullable', Rule::in(['newest', 'oldest', 'number', 'amount_desc', 'amount_asc'])]]);
    }
}
