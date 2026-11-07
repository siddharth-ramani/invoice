<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceProductLink extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'cost_rate' => 'decimal:2',
    ];
}
