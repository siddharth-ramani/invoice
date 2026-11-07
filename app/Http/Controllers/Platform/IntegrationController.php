<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Business\Platform;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index()
    {
        return view('platform.integrations');
    }

    public function email(Request $r)
    {
        $data = $r->validate(['enabled' => 'required|boolean', 'confirm' => 'accepted']);
        app(Platform::class)->set('email.enabled', (bool) $data['enabled']);
        app(Platform::class)->audit('email.settings', 'platform', null, ['enabled' => (bool) $data['enabled']]);

        return back()->with('success', 'Email availability saved. SMTP is configured separately; no messages were sent by saving.');
    }
}
