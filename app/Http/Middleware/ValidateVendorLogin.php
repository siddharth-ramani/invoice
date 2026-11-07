<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateVendorLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('bizpilot.impersonation')) {
            return $next($request);
        }

        $context = $request->session()->get('bizpilot.impersonation');
        $valid = is_array($context)
            && $request->user()?->role === 'vendor'
            && $request->user()->id === ($context['vendor_id'] ?? null)
            && User::whereKey($context['admin_id'] ?? null)->where('role', 'admin')->exists();

        if (! $valid) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your admin access session has ended. Please sign in again.']);
        }

        return $next($request);
    }
}
