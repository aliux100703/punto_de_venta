<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = ['folio', 'sale_id', 'user_id', 'cash_register_id', 'payment_method_id', 'type', 'payment_method_name', 'is_cash', 'subtotal', 'discount', 'taxes', 'total', 'reason', 'external_reference', 'request_key', 'payload_hash'];

    protected function casts(): array
    {
        return ['is_cash' => 'boolean', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'taxes' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
