<?php

namespace App\Http\Requests;

use App\Services\Business\BusinessSetup;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionPlanRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'regex:/^\d{1,8}(\.\d{1,2})?$/D', 'min:0', 'max:99999999.99'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:36500'],
            'invoice_limit' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'status' => ['required', 'boolean'],
            'original_version' => [$this->isMethod('PUT') || $this->isMethod('PATCH') ? 'required' : 'nullable', 'string', 'size:64'],
            'features' => ['sometimes', 'array:quotations,catalog,expenses,inventory,analytics'],
        ];
        foreach (BusinessSetup::FEATURES as $feature => $label) {
            $rules['features.'.$feature] = ['required_with:features', 'boolean'];
        }

        return $rules;
    }
}
