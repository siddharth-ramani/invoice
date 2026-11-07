<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountControl;
use App\Models\User;
use App\Services\Business\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    // Show login page
    public function create(Request $request)
    {
        if (in_array($request->query('lang'), ['en', 'gu', 'hi'], true)) {
            app()->setLocale($request->query('lang'));
        }

        return response()->view('auth.login')->header('Cache-Control', 'no-store, private');
    }

    // Handle login
    public function store(Request $request)
    {
        if (in_array($request->input('locale'), ['en', 'gu', 'hi'], true)) {
            app()->setLocale($request->input('locale'));
            $request->session()->put('bizpilot.locale', $request->input('locale'));
        }
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|max:4096',
        ]);

        $email = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->value('email') ?? mb_strtolower(trim($data['email']));
        if (Auth::attempt(['email' => $email, 'password' => $data['password']])) {
            if (app(Platform::class)->ready() && AccountControl::where('user_id', Auth::id())->where('disabled', true)->exists()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors(['email' => 'This account is disabled. Contact the administrator.'])->withInput($request->only('email'));
            }
            $request->session()->regenerate();

            return redirect()->route(Auth::user()->role === 'customer' ? 'settings' : 'dashboard');
        }

        return back()->withErrors([
            'email' => 'Invalid email or password',
        ])->withInput($request->only('email'));
    }

    // Logout
    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
