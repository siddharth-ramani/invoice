<?php

namespace App\Models;

use App\Models\Concerns\ImmutableHistory;
use Illuminate\Database\Eloquent\Model;

class ExpenseEvent extends Model
{
    use ImmutableHistory;

    protected $guarded = ['id'];

    protected $casts = [
        'snapshot' => 'array',
    ];
}
