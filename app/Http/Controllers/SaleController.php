<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\Sale;
use App\Services\RefundPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date'.($request->filled('from') ? '|after_or_equal:from' : '')]);
        $query = Sale::visibleTo($request->user())->with(['customer', 'seller'])
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('folio', 'like', "%{$v}%"))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
        $total = (clone $query)->whereIn('status', ['COMPLETADA', 'DEVOLUCION_PARCIAL'])->sum(DB::raw('total - refunded_amount'));
        $refundedTotal = (clone $query)->sum('refunded_amount');
        $sales = $query->latest()->paginate(20)->withQueryString();

        return view('sales.index', compact('sales', 'total', 'refundedTotal'));
    }

    public function show(Sale $sale)
    {
        Gate::authorize('view', $sale);
        $sale->load(['customer', 'seller', 'details.product.unit', 'payments.paymentMethod', 'returns.user']);

        $payment = $sale->payments->first();
        $refundIsCash = $payment?->is_cash ?? (CashMovement::where('reference_type', 'SALE')->where('reference_id', $sale->id)->where('type', 'ENTRADA')->exists() || mb_strtolower(trim($payment?->paymentMethod?->name ?? '')) === 'efectivo');

        $refundIsCash = $sale->payments->count() === 1 && $refundIsCash;
        $refundBlockReason = RefundPolicy::rejectionReason($sale, $refundIsCash);

        return view('sales.show', compact('sale', 'refundIsCash', 'refundBlockReason'));
    }
}
