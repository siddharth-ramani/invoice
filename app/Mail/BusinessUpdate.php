<?php

namespace App\Mail;

use App\Models\BusinessNotification;
use Illuminate\Mail\Mailable;

class BusinessUpdate extends Mailable
{
    public function __construct(public BusinessNotification $notification) {}

    public function build()
    {
        return $this->subject(__($this->notification->title))->text('notifications.email');
    }
}
