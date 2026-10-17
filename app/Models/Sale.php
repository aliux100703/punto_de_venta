<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'folio',
        'customer_id',
        'seller_id',
        'subtotal',
        'discount',
        'taxes',
        'total',
        'status',
        'notes',
        'refunded_amount',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'taxes' => 'decimal:2',
            'total' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(! $user->isAdministrator(), fn ($query) => $query->where('seller_id', $user->id));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function getNetTotalAttribute(): float
    {
        return in_array($this->status, ['CANCELADA', 'DEVUELTA']) ? 0 : max(0, round((float) $this->total - (float) $this->refunded_amount, 2));
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
