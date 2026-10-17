<?php

namespace App\Services;

use App\Models\Sale;

class RefundPolicy
{
    public const TEXT = 'Reembolsos únicamente en efectivo dentro de las 72 horas posteriores a la compra. No hay devoluciones ni reembolsos en pagos con tarjeta o transferencia.';

    public static function rejectionReason(Sale $sale, bool $isCash): ?string
    {
        if (! $isCash) {
            return 'No se permiten devoluciones ni cancelaciones con reembolso de pagos con tarjeta o transferencia.';
        }
        if (! $sale->created_at || $sale->created_at->isFuture()) {
            return 'La fecha original de la venta no es válida para un reembolso.';
        }
        if (now()->greaterThan($sale->created_at->copy()->addHours(72))) {
            return 'El plazo de 72 horas desde la compra ha vencido. No se permiten devoluciones ni cancelaciones con reembolso.';
        }

        return null;
    }
}
