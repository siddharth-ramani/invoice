<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceReceivable extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['original_total' => 'decimal:2', 'confirmed_at' => 'datetime'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
