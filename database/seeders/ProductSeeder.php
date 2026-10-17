<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::whereHas('role', fn ($query) => $query->where('name', Role::ADMINISTRATOR))->orderBy('id')->first();
        if (! $user) {
            throw new RuntimeException('Crea o asigna un administrador antes de cargar productos de demostración: el inventario necesita un usuario responsable.');
        }

        $created = 0;
        $skipped = 0;
        DB::transaction(function () use ($user, &$created, &$skipped) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->call([CategorySeeder::class, UnitSeeder::class]);
            $categories = Category::all()->keyBy('name');
            $units = Unit::all()->keyBy('name');
            $brands = [];

            foreach ($this->catalog() as $index => $row) {
                [$name, $category, $brandName, $unitName, $cost, $price, $stock, $minimum] = $row;
                $sku = 'DEMO-FER-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
                if (Product::where('sku', $sku)->exists()) {
                    $skipped++;

                    continue;
                }

                $barcode = $this->barcode($index + 1);
                if (Product::where('barcode', $barcode)->exists()) {
                    throw new RuntimeException("El código {$barcode} ya pertenece a otro producto. No se modificó ningún registro; resuelve el conflicto antes de reintentar.");
                }
                $unit = $units->get($unitName);
                if (! $unit || (! $unit->allows_decimals && (floor($stock) != $stock || floor($minimum) != $minimum))) {
                    throw new RuntimeException("La unidad {$unitName} no es compatible con las cantidades del producto {$name}.");
                }
                $brand = $brands[$brandName] ??= Brand::firstOrCreate(['name' => $brandName], ['active' => true]);
                $product = Product::create([
                    'category_id' => $categories->get($category)->id,
                    'brand_id' => $brand->id,
                    'unit_id' => $unit->id,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'name' => $name,
                    'description' => 'Producto de demostración para probar el POS. Costos, precios y códigos son ficticios; sustituir antes de operar con mercancía real.',
                    'cost' => $cost,
                    'sale_price' => $price,
                    'tax_percentage' => 16,
                    'current_stock' => 0,
                    'minimum_stock' => $minimum,
                    'active' => true,
                ]);
                if ($stock > 0) {
                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'type' => 'ENTRADA',
                        'quantity' => $stock,
                        'previous_stock' => 0,
                        'new_stock' => $stock,
                        'reference_type' => 'PRODUCT_SEED',
                        'reference_id' => $product->id,
                        'reason' => 'Existencia inicial de demostración (ProductSeeder)',
                    ]);
                    $product->update(['current_stock' => $stock]);
                }
                $created++;
            }
        });
        $this->command?->info("Productos demo creados: {$created}. Productos existentes conservados: {$skipped}.");
    }

    private function barcode(int $sequence): string
    {
        // Internal demo codes with an EAN-13 check digit, not manufacturer codes.
        $body = '200000'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $sum += (int) $body[$index] * ($index % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }

    private function catalog(): array
    {
        // Name, category, demo brand, unit, cost, base price, initial stock, minimum.
        return [
            ['Martillo de uña 16 oz', 'Herramientas', 'DemoPro', 'Pieza', 95, 149, 24, 5],
            ['Desarmador Phillips número 2', 'Herramientas', 'DemoPro', 'Pieza', 28, 49, 40, 8],
            ['Desarmador plano 1/4 pulgada', 'Herramientas', 'DemoPro', 'Pieza', 25, 45, 35, 8],
            ['Pinza de electricista 8 pulgadas', 'Herramientas', 'DemoPro', 'Pieza', 85, 139, 18, 4],
            ['Llave ajustable 10 pulgadas', 'Herramientas', 'DemoPro', 'Pieza', 110, 179, 3, 5],
            ['Flexómetro de 5 metros', 'Herramientas', 'DemoPro', 'Pieza', 52, 89, 30, 6],
            ['Broca para concreto 1/4 pulgada', 'Herramientas', 'DemoPro', 'Pieza', 18, 35, 60, 12],
            ['Arco para segueta 12 pulgadas', 'Herramientas', 'DemoPro', 'Pieza', 65, 109, 15, 3],
            ['Tornillo galvanizado 1/4 x 1 pulgada', 'Tornillería', 'Genérica Demo', 'Pieza', 1.2, 2.5, 400, 80],
            ['Tuerca hexagonal 1/4 pulgada', 'Tornillería', 'Genérica Demo', 'Pieza', 0.5, 1, 500, 100],
            ['Rondana plana 1/4 pulgada', 'Tornillería', 'Genérica Demo', 'Pieza', 0.25, 0.75, 600, 100],
            ['Taquete de plástico 1/4 pulgada', 'Tornillería', 'Genérica Demo', 'Bolsa', 18, 32, 45, 10],
            ['Clavo estándar 2 pulgadas a granel', 'Tornillería', 'Genérica Demo', 'Kilogramo', 32, 55, 42.5, 5],
            ['Codo de PVC hidráulico 1/2 pulgada', 'Plomería', 'AquaDemo', 'Pieza', 4, 8, 80, 15],
            ['Tubo de PVC hidráulico 1/2 pulgada', 'Plomería', 'AquaDemo', 'Metro', 12, 22, 75.5, 10],
            ['Cinta selladora de PTFE', 'Plomería', 'AquaDemo', 'Rollo', 5, 12, 70, 12],
            ['Llave de paso 1/2 pulgada', 'Plomería', 'AquaDemo', 'Pieza', 55, 95, 20, 5],
            ['Pegamento para PVC 125 ml', 'Plomería', 'AquaDemo', 'Pieza', 25, 45, 24, 5],
            ['Cable de cobre calibre 12', 'Electricidad', 'ElectroDemo', 'Metro', 9.5, 16, 185.75, 25],
            ['Contacto dúplex blanco', 'Electricidad', 'ElectroDemo', 'Pieza', 22, 39, 38, 8],
            ['Apagador sencillo blanco', 'Electricidad', 'ElectroDemo', 'Pieza', 16, 29, 42, 8],
            ['Cinta aislante negra 18 metros', 'Electricidad', 'ElectroDemo', 'Rollo', 8, 18, 55, 10],
            ['Foco LED 9 W luz blanca', 'Electricidad', 'ElectroDemo', 'Pieza', 25, 49, 4, 10],
            ['Brocha de 2 pulgadas', 'Pintura', 'ColorDemo', 'Pieza', 18, 35, 28, 5],
            ['Rodillo para pintar 9 pulgadas', 'Pintura', 'ColorDemo', 'Pieza', 42, 75, 22, 5],
            ['Pintura vinílica blanca a granel', 'Pintura', 'ColorDemo', 'Litro', 48, 79, 62.5, 10],
            ['Thinner a granel', 'Pintura', 'ColorDemo', 'Litro', 24, 42, 36.75, 8],
            ['Lija para madera grano 120', 'Pintura', 'ColorDemo', 'Pieza', 4, 9, 100, 20],
            ['Cemento gris saco de 50 kg', 'Construcción', 'Genérica Demo', 'Pieza', 185, 225, 30, 8],
            ['Yeso a granel', 'Construcción', 'Genérica Demo', 'Kilogramo', 4, 8, 120.5, 20],
            ['Espátula flexible de 4 pulgadas', 'Construcción', 'DemoPro', 'Pieza', 22, 42, 20, 4],
            ['Guantes de carnaza', 'Construcción', 'DemoPro', 'Pieza', 30, 55, 35, 8],
            ['Manguera para jardín 1/2 pulgada', 'Jardinería', 'Jardín Demo', 'Metro', 14, 25, 85.25, 15],
            ['Tijera para podar 8 pulgadas', 'Jardinería', 'Jardín Demo', 'Pieza', 68, 119, 14, 4],
            ['Pala de punta con mango', 'Jardinería', 'Jardín Demo', 'Pieza', 115, 189, 2, 4],
        ];
    }
}
