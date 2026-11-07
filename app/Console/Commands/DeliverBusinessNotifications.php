<?php

namespace App\Console\Commands;

use App\Services\Business\EmailOutbox;
use Illuminate\Console\Command;

class DeliverBusinessNotifications extends Command
{
    protected $signature = 'bizpilot:deliver-notifications {--limit=25}';

    protected $description = 'Submit explicitly requested, opted-in notification emails to the configured mail transport';

    public function handle(): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $count = app(EmailOutbox::class)->deliver($limit);
        $this->info($count.' notification emails submitted to the configured mail transport.');

        return self::SUCCESS;
    }
}
