<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Business\Platform;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    public function create(Request $request)
    {
        if (in_array($request->query('lang'), ['en', 'gu', 'hi'], true)) {
            app()->setLocale($request->query('lang'));
        }

        return response()->view('auth.register')->header('Cache-Control', 'no-store, private');
    }

    public function store(RegisterRequest $request)
    {
        $data = $request->validated();
        $locale = $data['locale'] ?? app()->getLocale();
        try {
            $user = DB::transaction(function () use ($data, $locale) {
                $user = User::forceCreate(['name' => $data['name'], 'company_name' => $data['company_name'], 'email' => $data['email'], 'mobile' => $data['mobile'] ?? null, 'password' => Hash::make($data['password']), 'role' => 'vendor', 'created_by' => null, 'invoice_prefix' => 'INV']);
                if (app(Platform::class)->ready()) {
                    UserPreference::create(['user_id' => $user->id, 'locale' => in_array($locale, ['en', 'gu', 'hi'], true) ? $locale : 'en', 'reminders_enabled' => false, 'email_enabled' => false]);
                    AuditEvent::create(['actor_id' => $user->id, 'vendor_id' => $user->id, 'action' => 'account.registered', 'target_type' => 'user', 'target_id' => $user->id, 'details' => ['role' => 'vendor', 'subscription_assigned' => false]]);
                }

                return $user;
            }, 5);
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[1] ?? null, [1062, 19], true) && User::whereRaw('LOWER(email) = ?', [$data['email']])->exists()) {
                throw ValidationException::withMessages(['email' => __('authflow.email_exists')]);
            }
            throw $e;
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('bizpilot.locale', $locale);

        return redirect()->route(app(Platform::class)->ready() ? 'business.setup' : 'settings')->with('success', __('authflow.registered'));
    }
}
