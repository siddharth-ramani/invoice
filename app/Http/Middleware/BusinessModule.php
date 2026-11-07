<?php

namespace App\Http\Middleware;

use App\Services\Business\Modules;
use App\Services\Business\Platform;
use Closure;
use Illuminate\Http\Request;

class BusinessModule
{
    public function handle(Request $request, Closure $next, string $table)
    {
        abort_unless($request->user()?->role === 'vendor', 403);
        app(Modules::class)->require($table);
        $feature = ['products' => 'catalog', 'quotations' => 'quotations', 'expenses' => ($request->routeIs('analytics.*') ? 'analytics' : 'expenses')][$table] ?? $table;
        if ($request->routeIs('inventory.*')) {
            $feature = 'inventory';
        }
        if ((! $request->isMethod('GET') || $feature === 'analytics') && ! $request->routeIs('expenses.void')) {
            abort_unless(app(Platform::class)->allowance($request->user()->id, $feature)['enabled'], 403, 'This feature is disabled for your plan. Contact the administrator.');
        }

        return $next($request);
    }
}
