<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'customer_id',
        'invoice_no',
        'invoice_date',
        'taxable_amount',
        'discount',
        'discount_percent',
        'cgst_total',
        'sgst_total',
        'total_tax',
        'grand_total',
        'round_off',
        'total_before_round',
        'amount_in_words',
        'notes',
    ];

    // Vendor (creator)
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    // Customer
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    // Invoice items
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
