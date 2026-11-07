<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'sr_no',
        'item_name',
        'hsn_sac',
        'qty',
        'rate',
        'taxable_value',
        'cgst_percent',
        'cgst_amount',
        'sgst_percent',
        'sgst_amount',
        'line_total',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
