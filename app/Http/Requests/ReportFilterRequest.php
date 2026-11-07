<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'vendor';
    }

    public function rules(): array
    {
        return ['from_date' => ['bail', 'nullable', 'string', 'date_format:Y-m-d'], 'to_date' => ['bail', 'nullable', 'string', 'date_format:Y-m-d']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->input('from_date') || ! $this->input('to_date')) {
                return;
            }
            if (CarbonImmutable::parse($this->input('to_date'))->lt(CarbonImmutable::parse($this->input('from_date')))) {
                $validator->errors()->add('to_date', 'The to date must be on or after the from date.');
            }
        });
    }
}
