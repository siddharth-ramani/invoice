<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['recipient' => 'encrypted', 'submitted_at' => 'datetime'];

    public function notification()
    {
        return $this->belongsTo(BusinessNotification::class, 'business_notification_id');
    }
}
