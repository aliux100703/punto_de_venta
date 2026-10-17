<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    protected $fillable = [
        'cash_register_id',
        'user_id',
        'type',
        'concept',
        'amount',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function cashRegister()
    {
        return $this->belongsTo(
            CashRegister::class
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
