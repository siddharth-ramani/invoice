<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'vendor'], true);
    }

    protected function prepareForValidation(): void
    {
        $today = CarbonImmutable::now(config('bizpilot.timezone'));
        $this->merge([
            'from_date' => $this->input('from_date') ?: $today->startOfYear()->toDateString(),
            'to_date' => $this->input('to_date') ?: $today->toDateString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'from_date' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
            'to_date' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Validate types first: malformed array query parameters must never reach Carbon.
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $from = CarbonImmutable::parse($this->input('from_date'), config('bizpilot.timezone'));
            $to = CarbonImmutable::parse($this->input('to_date'), config('bizpilot.timezone'));
            if ($to->lessThan($from)) {
                $validator->errors()->add('to_date', 'The to date must be on or after the from date.');
            } elseif ($to->greaterThan($from->addYears(5)->subDay())) {
                $validator->errors()->add('to_date', 'Choose a date range of five years or less.');
            }
        });
    }
}
