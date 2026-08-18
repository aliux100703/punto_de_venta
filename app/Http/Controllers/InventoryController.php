<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;

class InventoryController extends Controller
{
    /**
     * Listado general de inventario.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $products = Product::with([
            'category',
            'brand',
            'unit'
        ])
        ->when($search, function ($query, $search) {
            $query->where(function ($q) use ($search) {

                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");

            });
        })
        ->orderBy('name')
        ->paginate(20)
        ->withQueryString();

        return view('inventory.index', compact(
            'products',
            'search'
        ));
    }


    /**
     * Historial de movimientos de un producto.
     */
    public function show(Product $product)
    {
        $product->load([
            'category',
            'brand',
            'unit'
        ]);

        $movements = $product
            ->inventoryMovements()
            ->with('user')
            ->latest()
            ->paginate(20);

        return view('inventory.show', compact(
            'product',
            'movements'
        ));
    }


    /**
     * Formulario para registrar movimiento.
     */
    public function createMovement(Product $product)
    {
        $product->load([
            'category',
            'brand',
            'unit'
        ]);

        return view(
            'inventory.movement',
            compact('product')
        );
    }


    /**
     * Guardar movimiento de inventario.
     */
    public function storeMovement(
        Request $request,
        Product $product
    ) {
        $validated = $request->validate([
            'type' => [
                'required',
                Rule::in([
                    'ENTRADA',
                    'AJUSTE_POSITIVO',
                    'AJUSTE_NEGATIVO',
                    'MERMA',
                ]),
            ],

            'quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'reason' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        try {

            DB::transaction(function () use (
                $validated,
                $product
            ) {

                /*
                 * Volvemos a consultar el producto y bloqueamos
                 * el registro mientras calculamos el stock.
                 */
                $lockedProduct = Product::where(
                    'id',
                    $product->id
                )
                ->lockForUpdate()
                ->firstOrFail();

                $previousStock = (float)
                    $lockedProduct->current_stock;

                $quantity = (float)
                    $validated['quantity'];

                /*
                 * Si la unidad no permite decimales,
                 * rechazamos cantidades decimales.
                 */
                $lockedProduct->load('unit');

                if (
                    !$lockedProduct->unit->allows_decimals &&
                    floor($quantity) != $quantity
                ) {
                    throw new Exception(
                        'Este producto no permite cantidades decimales.'
                    );
                }

                switch ($validated['type']) {

                    case 'ENTRADA':

                    case 'AJUSTE_POSITIVO':

                        $newStock =
                            $previousStock + $quantity;

                        break;


                    case 'AJUSTE_NEGATIVO':

                    case 'MERMA':

                        $newStock =
                            $previousStock - $quantity;

                        break;


                    default:

                        throw new Exception(
                            'Tipo de movimiento no válido.'
                        );
                }

                /*
                 * Nunca permitimos inventario negativo.
                 */
                if ($newStock < 0) {

                    throw new Exception(
                        'No existe suficiente inventario para realizar este movimiento.'
                    );
                }

                /*
                 * Crear historial.
                 */
                InventoryMovement::create([
                    'product_id' => $lockedProduct->id,

                    'user_id' => auth()->id(),

                    'type' => $validated['type'],

                    'quantity' => $quantity,

                    'previous_stock' => $previousStock,

                    'new_stock' => $newStock,

                    'reference_type' => null,

                    'reference_id' => null,

                    'reason' => $validated['reason'],
                ]);

                /*
                 * Actualizar existencia.
                 */
                $lockedProduct->update([
                    'current_stock' => $newStock,
                ]);
            });

            return redirect()
                ->route(
                    'inventory.show',
                    $product
                )
                ->with(
                    'success',
                    'Movimiento registrado correctamente.'
                );

        } catch (Exception $e) {

            return back()
                ->withInput()
                ->withErrors([
                    'inventory' => $e->getMessage()
                ]);
        }
    }
}