<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'totals' => 'array',
        'party_snapshot' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function events()
    {
        return $this->hasMany(QuotationEvent::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }
}
