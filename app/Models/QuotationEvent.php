<?php

namespace App\Models;

use App\Models\Concerns\ImmutableHistory;
use Illuminate\Database\Eloquent\Model;

class QuotationEvent extends Model
{
    use ImmutableHistory;

    protected $guarded = ['id'];

    protected $casts = [
        'snapshot' => 'array',
    ];
}
