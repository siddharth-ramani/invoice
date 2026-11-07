<?php

namespace App\Models;

use App\Models\Concerns\ImmutableHistory;
use Illuminate\Database\Eloquent\Model;

class BillingRecord extends Model
{
    use ImmutableHistory;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}
