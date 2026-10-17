<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quote;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    public function index()
    {
        $quotes = Quote::visibleTo(auth()->user())->with('customer')->latest()->paginate(20);

        return view('quotes.index', compact('quotes'));
    }

    public function create()
    {
        return view('quotes.create', [
            'customers' => Customer::where('active', true)->orderBy('name')->get(),
            'products' => Product::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('active', true)],
            'expiration_date' => 'required|date|after_or_equal:today', 'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => ['required', 'distinct', Rule::exists('products', 'id')->where('active', true)],
            'items.*.quantity' => 'required|numeric|decimal:0,3|gt:0|max:999999999.999',
        ]);
        $quote = DB::transaction(function () use ($data) {
            $quote = Quote::create(['folio' => 'TMP-'.bin2hex(random_bytes(12)), 'user_id' => auth()->id(), 'customer_id' => $data['customer_id'] ?? null, 'expiration_date' => $data['expiration_date'], 'notes' => $data['notes'] ?? null, 'status' => 'PENDIENTE']);
            foreach ($data['items'] as $item) {
                $product = Product::with('unit')->where('active', true)->findOrFail($item['product_id']);
                $quantity = (float) $item['quantity'];
                if (! $product->unit?->allows_decimals && floor($quantity) !== $quantity) {
                    throw ValidationException::withMessages(['items' => "{$product->name} no permite cantidades decimales."]);
                }
                $subtotal = round((float) $product->sale_price * $quantity, 2);
                $tax = round($subtotal * (float) $product->tax_percentage / 100, 2);
                if ($subtotal + $tax > 9999999999.99) {
                    throw ValidationException::withMessages(['items' => 'El importe supera el máximo permitido.']);
                }
                $quote->details()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $product->sale_price, 'tax_percentage' => $product->tax_percentage, 'subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax]);
            }
            $subtotal = $quote->details()->sum('subtotal');
            $tax = $quote->details()->sum('tax');
            if ($subtotal + $tax > 9999999999.99) {
                throw ValidationException::withMessages(['items' => 'El total supera el máximo permitido.']);
            }
            $quote->update(['folio' => 'C-'.str_pad($quote->id, 8, '0', STR_PAD_LEFT), 'subtotal' => $subtotal, 'taxes' => $tax, 'total' => round($subtotal + $tax, 2)]);

            return $quote;
        });

        return to_route('quotes.show', $quote)->with('success', 'Cotización registrada.');
    }

    public function show(Quote $quote)
    {
        Gate::authorize('view', $quote);
        $quote->load(['customer', 'details.product', 'sale']);
        $methods = PaymentMethod::where('active', true)->orderBy('name')->get();

        return view('quotes.show', compact('quote', 'methods'));
    }

    public function update(Request $request, Quote $quote)
    {
        Gate::authorize('update', $quote);
        $data = $request->validate(['status' => ['required', Rule::in(['ACEPTADA', 'RECHAZADA'])]]);
        DB::transaction(function () use ($quote, $data) {
            $locked = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'PENDIENTE' || $locked->expiration_date?->lt(today())) {
                throw ValidationException::withMessages(['status' => 'Solo puedes responder cotizaciones pendientes vigentes.']);
            }
            $locked->update($data);
        });

        return back()->with('success', 'Estado actualizado.');
    }

    public function convert(Request $request, Quote $quote, CheckoutService $checkout)
    {
        $data = $request->validate([
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('active', true)],
            'cash_received' => 'nullable|numeric|decimal:0,2|min:0|max:9999999999.99',
        ]);
        Gate::authorize('convert', $quote);
        $result = $checkout->checkout($request->user(), $data, $quote);

        return to_route('sales.show', $result['sale'])->with('success', 'Cotización cobrada. Cambio: $'.number_format($result['change'], 2));
    }
}
