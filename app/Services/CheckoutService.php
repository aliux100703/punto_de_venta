<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\InventoryMovement;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function checkout(User $user, array $data, ?Quote $quote = null): array
    {
        return DB::transaction(function () use ($user, $data, $quote) {
            // Serialize cash operations for each cashier, including opening an empty register.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $method = PaymentMethod::where('active', true)->find($data['payment_method_id']);
            if (! $method) {
                $this->invalid('payment_method_id', 'Selecciona un método de pago activo.');
            }
            $isCash = mb_strtolower(trim($method->name)) === 'efectivo';
            $cash = $isCash ? CashRegister::where('user_id', $user->id)->where('status', 'ABIERTA')->lockForUpdate()->first() : null;
            if ($isCash && ! $cash) {
                $this->invalid('payment_method_id', 'Debes abrir una caja antes de realizar ventas en efectivo.');
            }
            if ($quote) {
                $quote = Quote::with('details')->whereKey($quote->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('convert', $quote);
                if ($quote->sale_id || ! in_array($quote->status, ['PENDIENTE', 'ACEPTADA']) || $quote->expiration_date?->lt(today())) {
                    $this->invalid('quote', 'La cotización ya fue convertida, rechazada o está vencida.');
                }
                $items = $quote->details->map(fn ($line) => ['product_id' => $line->product_id, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'tax_percentage' => $line->tax_percentage])->all();
                $customerId = $quote->customer_id;
            } else {
                $items = $data['items'];
                $customerId = $data['customer_id'] ?? null;
            }
            if (! $items) {
                $this->invalid('items', 'Agrega al menos un producto.');
            }
            // Lock products in a stable order to reduce deadlocks between cashiers.
            usort($items, fn ($a, $b) => $a['product_id'] <=> $b['product_id']);
            $products = Product::with('unit')->whereIn('id', array_column($items, 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lines = [];
            $subtotalCents = 0;
            $taxCents = 0;
            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || ! $product->active) {
                    $this->invalid('items', 'Uno de los productos ya no está disponible.');
                }
                $quantity = (float) $item['quantity'];
                if ($quantity <= 0 || abs(round($quantity, 3) - $quantity) > 0.0000001) {
                    $this->invalid('items', 'Las cantidades deben ser positivas y tener como máximo tres decimales.');
                }
                if (! $product->unit?->allows_decimals && floor($quantity) !== $quantity) {
                    $this->invalid('items', "El producto {$product->name} no permite cantidades decimales.");
                }
                if ($quantity > (float) $product->current_stock) {
                    $this->invalid('items', "No hay suficiente existencia de {$product->name}.");
                }
                $price = (float) ($quote ? $item['unit_price'] : $product->sale_price);
                $rate = (float) ($quote ? $item['tax_percentage'] : $product->tax_percentage);
                $rawBase = round($price * $quantity * 100);
                if ($rawBase > 999999999999) {
                    $this->invalid('items', 'El importe de un producto supera el máximo permitido.');
                }
                $base = (int) $rawBase;
                $tax = (int) round($base * $rate / 100);
                $subtotalCents += $base;
                $taxCents += $tax;
                $lines[] = compact('product', 'quantity', 'price', 'rate', 'base', 'tax');
            }
            $totalCents = $subtotalCents + $taxCents;
            if ($totalCents > 999999999999) {
                $this->invalid('items', 'El total supera el importe máximo permitido.');
            }
            $receivedCents = $isCash && isset($data['cash_received']) ? (int) round((float) $data['cash_received'] * 100) : null;
            if ($isCash && ($receivedCents === null || $receivedCents < $totalCents)) {
                $this->invalid('cash_received', 'El efectivo recibido es menor al total.');
            }
            $sale = Sale::create(['folio' => 'TMP-'.bin2hex(random_bytes(12)), 'customer_id' => $customerId, 'seller_id' => $user->id, 'subtotal' => $subtotalCents / 100, 'discount' => 0, 'taxes' => $taxCents / 100, 'total' => $totalCents / 100, 'status' => 'COMPLETADA', 'notes' => $quote ? 'Cotización '.$quote->folio : null]);
            $sale->update(['folio' => 'V-'.str_pad($sale->id, 8, '0', STR_PAD_LEFT)]);
            foreach ($lines as $line) {
                ['product' => $product, 'quantity' => $quantity, 'price' => $price, 'rate' => $rate, 'base' => $base, 'tax' => $tax] = $line;
                $sale->details()->create(['product_id' => $product->id, 'quantity' => $quantity, 'allows_decimals' => (bool) $product->unit?->allows_decimals, 'unit_cost' => $product->cost, 'unit_price' => $price, 'discount' => 0, 'tax_percentage' => $rate, 'tax' => $tax / 100, 'subtotal' => $base / 100, 'total' => ($base + $tax) / 100]);
                $previous = (float) $product->current_stock;
                $next = round($previous - $quantity, 3);
                InventoryMovement::create(['product_id' => $product->id, 'user_id' => $user->id, 'type' => 'VENTA', 'quantity' => $quantity, 'previous_stock' => $previous, 'new_stock' => $next, 'reference_type' => 'SALE', 'reference_id' => $sale->id, 'reason' => 'Venta '.$sale->folio]);
                $product->update(['current_stock' => $next]);
            }
            $sale->payments()->create(['payment_method_id' => $method->id, 'amount' => $sale->total, 'is_cash' => $isCash, 'method_name' => $method->name]);
            if ($cash) {
                $cash->movements()->create(['user_id' => $user->id, 'type' => 'ENTRADA', 'concept' => 'Venta '.$sale->folio, 'amount' => $sale->total, 'reference_type' => 'SALE', 'reference_id' => $sale->id]);
            }
            if ($quote) {
                $quote->update(['status' => 'CONVERTIDA', 'sale_id' => $sale->id]);
            }

            return ['sale' => $sale, 'cash_received' => $receivedCents === null ? null : $receivedCents / 100, 'change' => $isCash ? ($receivedCents - $totalCents) / 100 : 0];
        }, 3);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
