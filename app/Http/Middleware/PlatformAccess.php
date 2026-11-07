<?php

namespace App\Http\Middleware;

use App\Models\AccountControl;
use App\Models\UserPreference;
use App\Services\Business\Platform;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (app(Platform::class)->ready()) {
            $ids = array_filter([$request->user()?->id, $request->hasSession() ? $request->session()->get('bizpilot.impersonation.admin_id') : null]);
            if ($ids && AccountControl::whereIn('user_id', $ids)->where('disabled', true)->exists()) {
                if (! $request->hasSession()) {
                    abort(403, 'This account is disabled.');
                }
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'This account is disabled. Contact the administrator.']);
            }
            if ($request->user()) {
                $locale = UserPreference::where('user_id', $request->user()->id)->value('locale') ?? ($request->hasSession() ? $request->session()->get('bizpilot.locale', 'en') : 'en');
                app()->setLocale(in_array($locale, ['en', 'gu', 'hi'], true) ? $locale : 'en');
            } elseif ($request->hasSession()) {
                app()->setLocale(in_array($request->session()->get('bizpilot.locale'), ['en', 'gu', 'hi'], true) ? $request->session()->get('bizpilot.locale') : 'en');
            }
        }

        return $next($request);
    }
}
