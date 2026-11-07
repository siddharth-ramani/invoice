<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user();
    }

    protected function prepareForValidation(): void
    {
        if (in_array($this->input('locale'), ['en', 'gu', 'hi'], true)) {
            app()->setLocale($this->input('locale'));
        }
        foreach (['name', 'company_name', 'email', 'mobile'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => $key === 'email' ? mb_strtolower(trim($this->input($key))) : trim($this->input($key))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255', function ($key, $value, $fail) {
                if (User::whereRaw('LOWER(email) = ?', [$value])->exists()) {
                    $fail(__('authflow.email_exists'));
                }
            }],
            'mobile' => ['nullable', 'string', 'max:15'],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed', function ($key, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail(__('authflow.password_long'));
                } elseif (! preg_match('/\pL/u', $value) || ! preg_match('/\pN/u', $value)) {
                    $fail(__('authflow.password_rule'));
                }
            }],
            'locale' => ['sometimes', 'in:en,gu,hi'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => __('authflow.name'), 'company_name' => __('authflow.business'), 'email' => __('authflow.email'), 'password' => __('authflow.password')];
    }
}
