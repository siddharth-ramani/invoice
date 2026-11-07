<?php

namespace App\Models;

use App\Models\Concerns\ImmutableHistory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionEvent extends Model
{
    use ImmutableHistory;

    protected $guarded = ['id'];

    protected $casts = [
        'snapshot' => 'array',
    ];
}
