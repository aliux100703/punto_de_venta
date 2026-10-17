<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    protected $fillable = ['sale_return_id', 'sale_detail_id', 'product_id', 'product_name', 'quantity', 'restock', 'subtotal', 'discount', 'tax', 'total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'restock' => 'boolean', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleDetail(): BelongsTo
    {
        return $this->belongsTo(SaleDetail::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
