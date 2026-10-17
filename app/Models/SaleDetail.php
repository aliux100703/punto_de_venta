<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetail extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_cost',
        'unit_price',
        'discount',
        'tax_percentage',
        'tax',
        'subtotal',
        'total',
        'returned_quantity', 'refunded_total', 'refunded_tax', 'refunded_discount', 'allows_decimals',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'tax' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'returned_quantity' => 'decimal:3', 'refunded_total' => 'decimal:2', 'refunded_tax' => 'decimal:2', 'refunded_discount' => 'decimal:2', 'allows_decimals' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
