<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'email_enabled' => 'boolean', 'reminders_enabled' => 'boolean',
    ];
}
