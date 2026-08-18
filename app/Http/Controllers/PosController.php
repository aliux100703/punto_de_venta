<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index()
    {
        $paymentMethods = PaymentMethod::where('active', true)
            ->orderBy('name')
            ->get();

        return view('pos.index', compact('paymentMethods'));
    }

    public function searchProduct(Request $request)
    {
        $search = trim($request->get('search', ''));

        if ($search === '') {
            return response()->json([
                'success' => false,
                'message' => 'Ingresa un código o nombre de producto.'
            ], 422);
        }

        $product = Product::with('unit')
            ->where('active', true)
            ->where(function ($query) use ($search) {
                $query->where('barcode', $search)
                    ->orWhere('sku', $search)
                    ->orWhere('name', 'like', "%{$search}%");
            })
            ->orderByRaw("
                CASE
                    WHEN barcode = ? THEN 1
                    WHEN sku = ? THEN 2
                    ELSE 3
                END
            ", [$search, $search])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'price' => (float) $product->sale_price,
                'tax_percentage' => (float) $product->tax_percentage,
                'stock' => (float) $product->current_stock,
                'unit' => $product->unit?->abbreviation,
                'allows_decimals' => (bool) $product->unit?->allows_decimals,
            ]
        ]);
    }
}