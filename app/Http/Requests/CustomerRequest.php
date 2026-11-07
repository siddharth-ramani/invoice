<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer ? $this->user()->can('update', $customer) : $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $newVendor = ! $customer && $this->user()->role === 'admin';

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [$newVendor ? 'required' : 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer?->id)],
            'mobile' => ['nullable', 'string', 'max:15'],
            'gst_number' => ['nullable', 'string', 'max:15'],
            'address' => ['nullable', 'string', 'max:5000'],
            'password' => [$newVendor ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
