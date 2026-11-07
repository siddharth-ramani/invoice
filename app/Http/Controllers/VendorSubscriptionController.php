<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

class VendorSubscriptionController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->role === 'vendor', 403);
        $subscription = auth()->user()->activeSubscription()->with('plan')->first();
        $isExpired = $subscription && Carbon::today()->gt($subscription->end_date);

        return view('vendor.subscription', compact('subscription', 'isExpired'));
    }
}
