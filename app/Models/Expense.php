<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function attachments()
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function events()
    {
        return $this->hasMany(ExpenseEvent::class);
    }
}
