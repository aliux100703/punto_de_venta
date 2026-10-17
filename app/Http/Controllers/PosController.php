<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function index()
    {
        $paymentMethods = PaymentMethod::where('active', true)->orderBy('name')->get();
        $customers = Customer::where('active', true)->orderBy('name')->get();

        $activeCashRegister = auth()->user()->cashRegisters()->where('status', 'ABIERTA')->latest('opened_at')->first();
        $quickProducts = Product::with('unit')->where('active', true)->where('current_stock', '>', 0)->orderBy('name')->limit(8)->get()->map(fn ($product) => [
            'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode,
            'price' => (float) $product->sale_price, 'tax_percentage' => (float) $product->tax_percentage,
            'stock' => (float) $product->current_stock, 'unit' => $product->unit?->abbreviation,
            'allows_decimals' => (bool) $product->unit?->allows_decimals,
        ]);

        return view('pos.index', compact('paymentMethods', 'customers', 'quickProducts', 'activeCashRegister'));
    }

    public function searchProduct(Request $request)
    {
        $data = $request->validate(['search' => 'required|string|max:200']);
        $search = trim($data['search']);
        $query = Product::with('unit')->where('active', true);
        $product = (clone $query)->where(fn ($q) => $q->where('barcode', $search)->orWhere('sku', $search))->first()
            ?? $query->where('name', 'like', "%{$search}%")->orderBy('name')->first();
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado.'], 404);
        }

        return response()->json(['success' => true, 'product' => [
            'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode,
            'price' => (float) $product->sale_price, 'tax_percentage' => (float) $product->tax_percentage,
            'stock' => (float) $product->current_stock, 'unit' => $product->unit?->abbreviation,
            'allows_decimals' => (bool) $product->unit?->allows_decimals,
        ]]);
    }

    public function storeSale(Request $request, CheckoutService $checkout)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('active', true)],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('active', true)],
            'cash_received' => 'nullable|numeric|decimal:0,2|min:0|max:9999999999.99',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id',
            'items.*.quantity' => 'required|numeric|decimal:0,3|gt:0|max:999999999.999',
        ]);
        $result = $checkout->checkout($request->user(), $data);
        $sale = $result['sale'];

        return response()->json(['success' => true, 'message' => 'Venta realizada correctamente.', 'sale' => [
            'id' => $sale->id, 'folio' => $sale->folio, 'subtotal' => (float) $sale->subtotal,
            'taxes' => (float) $sale->taxes, 'total' => (float) $sale->total,
            'cash_received' => $result['cash_received'], 'change' => $result['change'],
            'url' => route('sales.show', $sale),
        ]]);
    }
}
