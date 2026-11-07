<?php

namespace App\Services\Business;

use App\Models\BusinessNotification;
use App\Models\UserPreference;
use App\Services\Payments\PaymentQueries;
use Illuminate\Support\Facades\DB;

class Notifications
{
    public function add(int $user, string $key, string $title, string $body, string $target, array $parameters = []): void
    {
        if (! app(Platform::class)->ready()) {
            return;
        }BusinessNotification::firstOrCreate(['user_id' => $user, 'event_key' => $key], ['title' => $title, 'body' => $body, 'target' => $target, 'parameters' => $parameters]);
    }

    public function reminders(int $vendor): int
    {
        if (! UserPreference::where('user_id', $vendor)->where('reminders_enabled', true)->exists()) {
            Guard::fail('reminders', 'Enable in-app reminders before generating them.');
        }
        $query = DB::query()->fromSub(app(PaymentQueries::class)->balances($vendor), 'b')->whereNotNull('confirmed_at')->where('outstanding_cents', '>', 0)->where('due_date', '<', now(config('bizpilot.timezone'))->toDateString())->orderBy('id');
        $count = 0;
        foreach ($query->cursor() as $r) {
            $this->add($vendor, 'overdue:'.$r->id.':'.now(config('bizpilot.timezone'))->format('Y-m-d'), 'Invoice overdue', 'Invoice :number has a reviewed overdue balance. No message has been sent to the customer.', route('invoices.show', $r->id, false), ['number' => $r->invoice_no]);
            $count++;
        }

        return $count;
    }
}
