<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\BusinessNotification;
use App\Models\NotificationDelivery;
use App\Models\UserPreference;
use App\Services\Business\EmailOutbox;
use App\Services\Business\Notifications;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function guestLocale(Request $request)
    {
        $data = $request->validate(['locale' => 'required|in:en,gu,hi']);
        $request->session()->put('bizpilot.locale', $data['locale']);

        return back();
    }

    public function index()
    {
        $notifications = BusinessNotification::where('user_id', auth()->id())->orderByDesc('id')->paginate(20);
        $preferences = UserPreference::where('user_id', auth()->id())->first();
        $deliveries = NotificationDelivery::where('user_id', auth()->id())->with('notification')->orderByDesc('id')->paginate(10, ['*'], 'email_page')->withQueryString();
        $emailReady = app(EmailOutbox::class)->ready();

        return view('notifications.index', compact('notifications', 'preferences', 'deliveries', 'emailReady'));
    }

    public function read(BusinessNotification $notification)
    {
        abort_unless((int) $notification->user_id === auth()->id(), 403);
        $notification->update(['read_at' => now()]);

        return back()->with('success', 'Notification marked as read.');
    }

    public function preferences(Request $r)
    {
        $data = $r->validate(['locale' => 'required|in:en,gu,hi', 'reminders_enabled' => 'required|boolean', 'email_enabled' => 'sometimes|boolean']);
        UserPreference::updateOrCreate(['user_id' => $r->user()->id], $data);

        return back()->with('success', 'Preferences saved.');
    }

    public function email(Request $r, BusinessNotification $notification)
    {
        $r->validate(['confirm' => 'accepted']);
        app(EmailOutbox::class)->queue($r->user(), $notification);

        return back()->with('success', 'Email request queued. The configured delivery worker submits it; no duplicate request is created.');
    }

    public function locale(Request $r)
    {
        $data = $r->validate(['locale' => 'required|in:en,gu,hi']);
        UserPreference::updateOrCreate(['user_id' => $r->user()->id], ['locale' => $data['locale']]);

        return back()->with('success', 'Language saved.');
    }

    public function reminders(Request $r)
    {
        abort_unless($r->user()->role === 'vendor', 403);
        $r->validate(['confirm' => 'accepted']);
        $count = app(Notifications::class)->reminders($r->user()->id);

        return back()->with('success', 'In-app overdue reminders checked: '.$count.'. No email or customer message was sent.');
    }
}
