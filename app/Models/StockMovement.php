<?php

namespace App\Models;

use App\Models\Concerns\ImmutableHistory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use ImmutableHistory;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
