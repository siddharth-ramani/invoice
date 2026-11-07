<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Business\BusinessSetup;

class BusinessSetupController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->role === 'vendor', 403);
        $setup = app(BusinessSetup::class)->summary(auth()->user());

        return view('platform.business-setup', compact('setup'));
    }
}
