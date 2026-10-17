<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\SaleReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SaleReturnController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100']);
        $adjustments = SaleReturn::with(['sale', 'user'])->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('folio', 'like', "%{$search}%")->orWhereHas('sale', fn ($sale) => $sale->where('folio', 'like', "%{$search}%"))))->latest()->paginate(20)->withQueryString();

        return view('sale-returns.index', compact('adjustments'));
    }

    public function cancel(Request $request, Sale $sale, SaleReturnService $service)
    {
        Gate::authorize('adjust', $sale);
        $data = $request->validate($this->rules());
        $adjustment = $service->process($request->user(), $sale, $data, 'CANCELACION');

        return to_route('sales.show', $sale)->with('success', 'Cancelación registrada: '.$adjustment->folio.'. Reembolso: $'.number_format($adjustment->total, 2));
    }

    public function store(Request $request, Sale $sale, SaleReturnService $service)
    {
        Gate::authorize('adjust', $sale);
        $data = $request->validate($this->rules() + [
            'items' => 'required|array|min:1|max:100', 'items.*.sale_detail_id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|numeric|decimal:0,3|min:0|max:999999999.999',
            'items.*.restock' => 'required|boolean',
        ]);
        $adjustment = $service->process($request->user(), $sale, $data, 'DEVOLUCION');

        return to_route('sales.show', $sale)->with('success', 'Devolución registrada: '.$adjustment->folio.'. Reembolso: $'.number_format($adjustment->total, 2));
    }

    public function show(Sale $sale, SaleReturn $saleReturn)
    {
        Gate::authorize('view', $sale);
        abort_unless($saleReturn->sale_id === $sale->id, 404);
        $saleReturn->load(['items', 'user', 'sale.customer', 'cashRegister']);

        return view('sale-returns.show', ['adjustment' => $saleReturn]);
    }

    private function rules(): array
    {
        return ['reason' => 'required|string|min:5|max:1000', 'request_key' => 'required|uuid', 'external_reference' => 'nullable|string|max:150', 'refund_confirmed' => 'sometimes|accepted'];
    }
}
