<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    private const FIELDS = ['name', 'email', 'role_id', 'active', 'sku', 'barcode', 'category_id', 'brand_id', 'unit_id', 'cost', 'sale_price', 'tax_percentage', 'minimum_stock', 'current_stock', 'allows_decimals', 'abbreviation', 'status', 'folio', 'total', 'subtotal', 'taxes', 'discount', 'refunded_amount', 'seller_id', 'user_id', 'customer_id', 'sale_id', 'product_id', 'cash_register_id', 'payment_method_id', 'type', 'quantity', 'previous_stock', 'new_stock', 'reference_type', 'reference_id', 'reason', 'concept', 'amount', 'opening_amount', 'expected_cash', 'counted_cash', 'difference', 'opened_at', 'closed_at', 'is_cash', 'payment_method_name'];

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $action): void
    {
        if (! auth()->check()) {
            return;
        }
        $keys = array_intersect(self::FIELDS, array_keys($action === 'updated' ? $model->getChanges() : $model->getAttributes()));
        $old = $action === 'created' ? [] : array_intersect_key($model->getRawOriginal(), array_flip($keys));
        $new = $action === 'deleted' ? [] : array_intersect_key($model->getAttributes(), array_flip($keys));
        // Only a flag is stored when a password changes; never its value or hash.
        if ($action === 'updated' && $model instanceof User && $model->wasChanged('password')) {
            $new['password_changed'] = true;
        }
        if (! $old && ! $new) {
            return;
        }
        AuditLogger::record($action, auth()->user(), class_basename($model), $model->getKey(), $old, $new);
    }
}
