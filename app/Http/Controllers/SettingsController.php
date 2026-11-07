<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    // Show settings
    public function settings()
    {
        $user = auth()->user();

        return view('settings.index', compact('user'));
    }

    // Update settings
    public function updateSettings(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'mobile_2' => 'nullable|string|max:15',
            'address' => 'nullable|string|max:5000',
            'terms_conditions' => 'nullable|string|max:20000',
            'mobile' => 'nullable|string|max:15',
            'gst_number' => 'nullable|string|max:20',
            'invoice_prefix' => 'nullable|string|max:3',
            'current_password' => $request->filled('password') ? ['bail', 'required', 'string', 'current_password:web'] : ['exclude'],
            'password' => ['bail', 'nullable', 'string', 'min:8', 'confirmed', function ($key, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail(__('authflow.password_long'));
                } elseif (! preg_match('/\pL/u', $value) || ! preg_match('/\pN/u', $value)) {
                    $fail(__('authflow.password_rule'));
                }
            }],
        ]);

        // Update common fields
        $user->name = $request->name;
        $user->company_name = $request->company_name;
        $user->email = $request->email;
        $user->mobile = $request->mobile;
        $user->address = $request->address;
        $user->mobile_2 = $request->mobile_2;
        $user->gst_number = $request->gst_number;
        $user->invoice_prefix = $request->invoice_prefix;
        $user->terms_conditions = $request->terms_conditions;

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return back()->with('success', 'Settings updated successfully');
    }
}
