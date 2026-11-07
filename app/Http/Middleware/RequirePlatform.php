<?php

namespace App\Http\Middleware;

use App\Services\Business\Platform;
use Closure;
use Illuminate\Http\Request;

class RequirePlatform
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app(Platform::class)->ready(), 503, 'Platform services are being installed.');

        return $next($request);
    }
}
