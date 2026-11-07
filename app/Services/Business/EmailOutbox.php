<?php

namespace App\Services\Business;

use App\Mail\BusinessUpdate;
use App\Models\AccountControl;
use App\Models\BusinessNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EmailOutbox
{
    public function ready(): bool
    {
        return app(Platform::class)->ready() && app(Platform::class)->setting('email.enabled', false) && in_array(config('mail.default'), ['smtp', 'ses', 'mailgun', 'postmark', 'sendmail'], true);
    }

    public function queue(User $user, BusinessNotification $notification): NotificationDelivery
    {
        abort_unless((int) $notification->user_id === $user->id, 403);
        if (! $this->ready()) {
            Guard::fail('email', 'Email delivery has not been enabled with a real mail transport.');
        }

        return DB::transaction(function () use ($user, $notification) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! UserPreference::where('user_id', $user->id)->where('email_enabled', true)->exists()) {
                Guard::fail('email', 'Opt in to email updates in preferences first.');
            }if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                Guard::fail('email', 'Your account needs a valid email address.');
            }

            return NotificationDelivery::firstOrCreate(['business_notification_id' => $notification->id], ['user_id' => $user->id, 'recipient' => $user->email, 'status' => 'queued']);
        }, 5);
    }

    public function deliver(int $limit = 25): int
    {
        if (! $this->ready()) {
            return 0;
        }$count = 0;
        $ids = NotificationDelivery::where('status', 'queued')->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            $delivery = DB::transaction(function () use ($id) {
                $row = NotificationDelivery::whereKey($id)->lockForUpdate()->first();
                if (! $row || $row->status !== 'queued') {
                    return null;
                }
                if (AccountControl::where('user_id', $row->user_id)->where('disabled', true)->exists() || ! UserPreference::where('user_id', $row->user_id)->where('email_enabled', true)->exists()) {
                    $row->update(['status' => 'failed', 'error_code' => 'recipient_unavailable_or_opted_out']);

                    return null;
                }
                $row->update(['status' => 'sending']);

                return $row;
            }, 5);
            if (! $delivery) {
                continue;
            }
            try {
                Mail::to($delivery->recipient)->send((new BusinessUpdate($delivery->notification))->locale(UserPreference::where('user_id', $delivery->user_id)->value('locale') ?? 'en'));
                $delivery->update(['status' => 'submitted', 'submitted_at' => now()]);
                $count++;
            } catch (\Throwable $error) {
                $delivery->update(['status' => 'failed', 'error_code' => 'mail_transport_failed']);
            }
        }

        return $count;
    }
}
