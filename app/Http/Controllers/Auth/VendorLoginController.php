<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountControl;
use App\Models\User;
use App\Services\Business\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class VendorLoginController extends Controller
{
    public function store(Request $request, User $vendor)
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_if($request->session()->has('bizpilot.impersonation'), 403);
        abort_unless($vendor->role === 'vendor', 403);
        if (app(Platform::class)->ready()) {
            abort_if(AccountControl::where('user_id', $vendor->id)->where('disabled', true)->exists(), 403, 'This vendor account is disabled.');
        }

        $adminId = $request->user()->id;
        // Only the server records the originating admin. Never accept it from input.
        $request->session()->flush();
        Auth::login($vendor, false);
        $request->session()->regenerate(true);
        $request->session()->put('bizpilot.impersonation', [
            'admin_id' => $adminId,
            'vendor_id' => $vendor->id,
        ]);
        Log::notice('admin_vendor_session_started', ['admin_id' => $adminId, 'vendor_id' => $vendor->id]);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request)
    {
        $context = $request->session()->get('bizpilot.impersonation');
        abort_unless(is_array($context), 403);
        $admin = User::whereKey($context['admin_id'] ?? null)->where('role', 'admin')->first();
        abort_unless($admin && $request->user()->role === 'vendor' && $request->user()->id === ($context['vendor_id'] ?? null), 403);

        $vendorId = $request->user()->id;
        $request->session()->flush();
        Auth::login($admin, false);
        $request->session()->regenerate(true);
        Log::notice('admin_vendor_session_ended', ['admin_id' => $admin->id, 'vendor_id' => $vendorId]);

        return redirect()->route('customers.index')->with('success', 'Returned to your admin account.');
    }
}
