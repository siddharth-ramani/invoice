<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    protected $fillable = [
        'name',
        'price',
        'duration_days',
        'invoice_limit',
        'status',
    ];
}
