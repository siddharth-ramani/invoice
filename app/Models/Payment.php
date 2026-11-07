<?php

namespace App\Models;

use App\Models\Concerns\ImmutableFinancialRecord;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use ImmutableFinancialRecord;

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2', 'party_snapshot' => 'array'];

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function reversal()
    {
        return $this->hasOne(PaymentReversal::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
