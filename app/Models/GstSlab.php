<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GstSlab extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'rate'];

    public function vendor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
