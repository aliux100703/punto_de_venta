<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with([
            'category',
            'brand',
            'unit',
        ])->orderBy('name')->paginate(20);

        return view('products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::where('active', true)
            ->orderBy('name')
            ->get();

        $brands = Brand::where('active', true)
            ->orderBy('name')
            ->get();

        $units = Unit::where('active', true)
            ->orderBy('name')
            ->get();

        return view('products.create', compact(
            'categories',
            'brands',
            'units'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => [
                'nullable',
                'exists:categories,id',
            ],

            'brand_id' => [
                'nullable',
                'exists:brands,id',
            ],

            'unit_id' => [
                'required',
                'exists:units,id',
            ],

            'sku' => [
                'nullable',
                'string',
                'max:100',
                'unique:products,sku',
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:100',
                'unique:products,barcode',
            ],

            'name' => [
                'required',
                'string',
                'max:200',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'cost' => [
                'required',
                'numeric',
                'max:9999999999.99',
                'min:0',
            ],

            'sale_price' => [
                'required',
                'numeric',
                'max:9999999999.99',
                'min:0',
            ],

            'tax_percentage' => [
                'required',
                'numeric',
                'max:9999999999.99',
                'min:0',
                'max:100',
            ],

            'minimum_stock' => [
                'required',
                'numeric',
                'max:999999999.999',
                'min:0',
            ],
        ]);

        /*
         * El stock NO lo capturamos aquí.
         * El inventario se modificará mediante movimientos.
         */
        $validated['current_stock'] = 0;
        $validated['active'] = true;

        Product::create($validated);

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function show(Product $product)
    {
        $product->load([
            'category',
            'brand',
            'unit',
            'inventoryMovements.user',
        ]);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories = Category::where('active', true)
            ->orderBy('name')
            ->get();

        $brands = Brand::where('active', true)
            ->orderBy('name')
            ->get();

        $units = Unit::where('active', true)
            ->orderBy('name')
            ->get();

        return view('products.edit', compact(
            'product',
            'categories',
            'brands',
            'units'
        ));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => [
                'nullable',
                'exists:categories,id',
            ],

            'brand_id' => [
                'nullable',
                'exists:brands,id',
            ],

            'unit_id' => [
                'required',
                'exists:units,id',
            ],

            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->ignore($product->id),
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')
                    ->ignore($product->id),
            ],

            'name' => [
                'required',
                'string',
                'max:200',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'cost' => [
                'required',
                'numeric',
                'max:9999999999.99',
                'min:0',
            ],

            'sale_price' => [
                'required',
                'numeric',
                'max:9999999999.99',
                'min:0',
            ],

            'tax_percentage' => [
                'required',
                'numeric',
                'max:9999999999.99',
                'min:0',
                'max:100',
            ],

            'minimum_stock' => [
                'required',
                'numeric',
                'max:999999999.999',
                'min:0',
            ],

            'active' => [
                'required',
                'boolean',
            ],
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);
        if (! $unit->allows_decimals && floor((float) $product->current_stock) != (float) $product->current_stock) {
            throw ValidationException::withMessages(['unit_id' => 'No puedes elegir una unidad entera mientras el inventario tenga fracciones.']);
        }
        $product->update($validated);

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product)
    {
        /*
         * No eliminamos físicamente el producto porque puede
         * tener ventas o movimientos históricos.
         */
        $product->update([
            'active' => false,
        ]);

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto desactivado correctamente.');
    }
}
