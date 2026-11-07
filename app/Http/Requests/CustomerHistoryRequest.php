<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CustomerHistoryRequest extends ReportFilterRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('customer'));
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['paid', 'unpaid', 'cancelled'])]]);
    }
}
