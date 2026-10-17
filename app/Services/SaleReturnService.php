<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaleReturnService
{
    public function process(User $actor, Sale $sale, array $data, string $type): SaleReturn
    {
        $items = collect($data['items'] ?? [])->filter(fn ($item) => (float) $item['quantity'] > 0)->map(fn ($item) => [
            'sale_detail_id' => (int) $item['sale_detail_id'],
            'units' => (int) round((float) $item['quantity'] * 1000),
            'restock' => (bool) $item['restock'],
        ])->sortBy('sale_detail_id')->values()->all();
        $confirmed = in_array($data['refund_confirmed'] ?? false, [true, 1, '1', 'yes', 'on'], true);
        $reference = trim($data['external_reference'] ?? '') ?: null;
        $hash = hash('sha256', json_encode(['actor' => $actor->id, 'sale' => $sale->id, 'type' => $type, 'items' => $items, 'reason' => trim($data['reason']), 'reference' => $reference, 'confirmed' => $confirmed], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $sale, $data, $type, $items, $hash, $confirmed, $reference) {
            $actor = User::with('role')->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('adjust', $sale);
            // Same lock order as checkout and register closure: user, register, products.
            $cash = CashRegister::where('user_id', $actor->id)->where('status', 'ABIERTA')->lockForUpdate()->first();
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $previousRequest = SaleReturn::where('request_key', $data['request_key'])->first();
            if ($previousRequest) {
                if (! hash_equals($previousRequest->payload_hash, $hash)) {
                    $this->invalid('request_key', 'Esta solicitud ya fue utilizada con otros datos. Actualiza la página.');
                }

                return $previousRequest;
            }
            if (! in_array($type, ['CANCELACION', 'DEVOLUCION'])) {
                $this->invalid('sale', 'Tipo de operación no válido.');
            }
            if (! in_array($sale->status, ['COMPLETADA', 'DEVOLUCION_PARCIAL'])) {
                $this->invalid('sale', 'Esta venta ya está cancelada o completamente devuelta.');
            }
            $details = SaleDetail::where('sale_id', $sale->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($details->isEmpty()) {
                $this->invalid('sale', 'La venta no tiene conceptos para procesar.');
            }
            if ($type === 'CANCELACION' && ($sale->status !== 'COMPLETADA' || $details->contains(fn ($detail) => (float) $detail->returned_quantity > 0))) {
                $this->invalid('sale', 'Una venta con devoluciones previas no se puede cancelar. Devuelve los productos restantes.');
            }
            $payments = SalePayment::with('paymentMethod')->where('sale_id', $sale->id)->orderBy('id')->lockForUpdate()->get();
            if ($payments->count() !== 1 || $this->cents($payments->sum('amount')) !== $this->cents($sale->total) || $this->cents($details->sum('total')) !== $this->cents($sale->total)) {
                $this->invalid('sale', 'La venta requiere conciliación: debe tener un solo método de pago y sus importes deben coincidir con los conceptos.');
            }
            if ($this->cents($details->sum('refunded_total')) !== $this->cents($sale->refunded_amount)) {
                $this->invalid('sale', 'Los importes devueltos no coinciden con el historial. Revisa la venta antes de continuar.');
            }
            $payment = $payments->first();
            $methodName = $payment->method_name ?? $payment->paymentMethod?->name ?? 'Pago original';
            $isCash = $payment->is_cash ?? (CashMovement::where('reference_type', 'SALE')->where('reference_id', $sale->id)->where('type', 'ENTRADA')->exists() || mb_strtolower(trim($methodName)) === 'efectivo');
            if ($reason = RefundPolicy::rejectionReason($sale, $isCash)) {
                $this->invalid('sale', $reason);
            }
            $products = Product::with('unit')->whereIn('id', $details->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $selected = $type === 'CANCELACION' ? $details->map(fn ($line) => ['sale_detail_id' => $line->id, 'units' => (int) round((float) $line->quantity * 1000), 'restock' => true])->values()->all() : $items;
            if (! $selected) {
                $this->invalid('items', 'Indica al menos una cantidad para devolver.');
            }
            if (count(array_unique(array_column($selected, 'sale_detail_id'))) !== count($selected)) {
                $this->invalid('items', 'No repitas el mismo concepto.');
            }
            $plans = [];
            $total = $taxes = $discount = $subtotal = 0;
            foreach ($selected as $item) {
                $line = $details->get($item['sale_detail_id']);
                if (! $line) {
                    $this->invalid('items', 'Uno de los conceptos no pertenece a esta venta.');
                }
                $sold = (int) round((float) $line->quantity * 1000);
                $returned = (int) round((float) $line->returned_quantity * 1000);
                $units = $item['units'];
                $next = $returned + $units;
                if ($sold <= 0 || $units <= 0 || $returned < 0 || $next > $sold) {
                    $this->invalid('items', 'La cantidad solicitada supera lo que queda por devolver.');
                }
                $product = $products->get($line->product_id);
                if (! $product) {
                    $this->invalid('items', 'No se encuentra el producto original.');
                }
                $allowsDecimals = $line->allows_decimals ?? (bool) $product->unit?->allows_decimals;
                if (! $allowsDecimals && $units % 1000 !== 0) {
                    $this->invalid('items', "El producto {$product->name} no permite cantidades decimales.");
                }
                // Cumulative allocation guarantees the last return refunds the exact remainder.
                $newTotal = $this->allocated($this->cents($line->total), $next, $sold);
                $newTax = $this->allocated($this->cents($line->tax), $next, $sold);
                $newDiscount = $this->allocated($this->cents($line->discount), $next, $sold);
                $lineTotal = $newTotal - $this->cents($line->refunded_total);
                $lineTax = $newTax - $this->cents($line->refunded_tax);
                $lineDiscount = $newDiscount - $this->cents($line->refunded_discount);
                $lineSubtotal = $lineTotal - $lineTax + $lineDiscount;
                if (min($lineTotal, $lineTax, $lineDiscount, $lineSubtotal) < 0) {
                    $this->invalid('items', 'El historial de importes del concepto no es válido.');
                }
                $plans[] = compact('line', 'product', 'units', 'next', 'newTotal', 'newTax', 'newDiscount', 'lineTotal', 'lineTax', 'lineDiscount', 'lineSubtotal') + ['restock' => $item['restock']];
                $total += $lineTotal;
                $taxes += $lineTax;
                $discount += $lineDiscount;
                $subtotal += $lineSubtotal;
            }
            $newRefunded = $this->cents($sale->refunded_amount) + $total;
            if ($newRefunded > $this->cents($sale->total)) {
                $this->invalid('sale', 'El reembolso excede el importe original de la venta.');
            }
            if ($isCash && $total > 0) {
                if (! $cash) {
                    $this->invalid('cash', 'Abre tu caja antes de devolver efectivo. La caja original de la venta no se modifica.');
                }
                $balance = $this->cents($cash->opening_amount) + $this->cents($cash->movements()->where('type', 'ENTRADA')->sum('amount')) - $this->cents($cash->movements()->where('type', 'SALIDA')->sum('amount'));
                if ($balance < $total) {
                    $this->invalid('cash', 'Tu caja no tiene efectivo suficiente para este reembolso.');
                }
            }
            $adjustment = SaleReturn::create([
                'folio' => 'TMP-'.bin2hex(random_bytes(12)), 'sale_id' => $sale->id, 'user_id' => $actor->id,
                'cash_register_id' => $isCash && $total > 0 ? $cash->id : null,
                'payment_method_id' => $payment->payment_method_id, 'type' => $type, 'payment_method_name' => $methodName, 'is_cash' => $isCash,
                'subtotal' => $subtotal / 100, 'discount' => $discount / 100, 'taxes' => $taxes / 100, 'total' => $total / 100,
                'reason' => trim($data['reason']), 'external_reference' => $isCash ? null : $reference,
                'request_key' => $data['request_key'], 'payload_hash' => $hash,
            ]);
            $adjustment->update(['folio' => ($type === 'CANCELACION' ? 'CAN-' : 'DEV-').str_pad($adjustment->id, 8, '0', STR_PAD_LEFT)]);
            foreach ($plans as $plan) {
                $line = $plan['line'];
                $product = $plan['product'];
                $quantity = $plan['units'] / 1000;
                $adjustment->items()->create([
                    'sale_detail_id' => $line->id, 'product_id' => $product->id, 'product_name' => $product->name,
                    'quantity' => $quantity, 'restock' => $plan['restock'], 'subtotal' => $plan['lineSubtotal'] / 100,
                    'discount' => $plan['lineDiscount'] / 100, 'tax' => $plan['lineTax'] / 100, 'total' => $plan['lineTotal'] / 100,
                ]);
                if ($plan['restock']) {
                    $previous = (float) $product->current_stock;
                    $newStock = round($previous + $quantity, 3);
                    if ($newStock > 999999999.999) {
                        $this->invalid('items', 'El reintegro excede el inventario máximo permitido.');
                    }
                    InventoryMovement::create(['product_id' => $product->id, 'user_id' => $actor->id, 'type' => 'DEVOLUCION', 'quantity' => $quantity, 'previous_stock' => $previous, 'new_stock' => $newStock, 'reference_type' => 'SALE_RETURN', 'reference_id' => $adjustment->id, 'reason' => $adjustment->folio.' / '.$sale->folio]);
                    $product->update(['current_stock' => $newStock]);
                }
                $line->update(['returned_quantity' => $plan['next'] / 1000, 'refunded_total' => $plan['newTotal'] / 100, 'refunded_tax' => $plan['newTax'] / 100, 'refunded_discount' => $plan['newDiscount'] / 100]);
            }
            if ($isCash && $total > 0) {
                $cash->movements()->create(['user_id' => $actor->id, 'type' => 'SALIDA', 'concept' => 'Reembolso '.$adjustment->folio.' / '.$sale->folio, 'amount' => $total / 100, 'reference_type' => 'SALE_RETURN', 'reference_id' => $adjustment->id, 'notes' => trim($data['reason'])]);
            }
            $hasRemaining = $details->contains(fn ($line) => (float) $line->returned_quantity < (float) $line->quantity);
            $sale->update(['refunded_amount' => $newRefunded / 100, 'status' => $type === 'CANCELACION' ? 'CANCELADA' : ($hasRemaining ? 'DEVOLUCION_PARCIAL' : 'DEVUELTA')]);

            return $adjustment;
        }, 3);
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function allocated(int $cents, int $units, int $sold): int
    {
        return $units === $sold ? $cents : (int) round($cents * ($units / $sold));
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
