<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountControl extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'disabled' => 'boolean',
    ];
}
