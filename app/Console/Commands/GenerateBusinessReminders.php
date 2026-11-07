<?php

namespace App\Console\Commands;

use App\Models\AccountControl;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Business\Notifications;
use App\Services\Business\Platform;
use Illuminate\Console\Command;

class GenerateBusinessReminders extends Command
{
    protected $signature = 'bizpilot:generate-reminders';

    protected $description = 'Generate opted-in in-app overdue reminders only; never email customers';

    public function handle(): int
    {
        if (! app(Platform::class)->ready()) {
            return self::SUCCESS;
        }$count = 0;
        foreach (UserPreference::where('reminders_enabled', true)->orderBy('id')->cursor() as $preference) {
            $user = User::whereKey($preference->user_id)->where('role', 'vendor')->first();
            if (! $user || AccountControl::where('user_id', $user->id)->where('disabled', true)->exists()) {
                continue;
            }$count += app(Notifications::class)->reminders($user->id);
        }
        $this->info($count.' overdue invoice checks completed for opted-in businesses. No customer messages were sent.');

        return self::SUCCESS;
    }
}
