<?php

namespace App\Http\Middleware;

use App\Services\Payments\PaymentModule;
use Closure;
use Illuminate\Http\Request;

class RequirePayments
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->role === 'vendor', 403);
        app(PaymentModule::class)->requireReady();

        return $next($request);
    }
}
