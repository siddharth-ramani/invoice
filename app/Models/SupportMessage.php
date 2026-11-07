<?php

namespace App\Models;

use App\Models\Concerns\ImmutableHistory;
use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    use ImmutableHistory;

    protected $guarded = ['id'];

    protected $casts = [
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
