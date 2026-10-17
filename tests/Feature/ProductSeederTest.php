<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_has_valid_barcodes_stock_and_inventory_history(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->seed(ProductSeeder::class);
        $this->assertDatabaseCount('products', 35);
        $this->assertDatabaseCount('inventory_movements', 35);
        foreach (Product::with(['category', 'brand', 'unit', 'inventoryMovements'])->get() as $product) {
            $this->assertNotNull($product->category);
            $this->assertNotNull($product->brand);
            $this->assertNotNull($product->unit);
            $this->assertGreaterThan(0, (float) $product->current_stock);
            $this->assertGreaterThan((float) $product->cost, (float) $product->sale_price);
            $this->assertMatchesRegularExpression('/^\d{13}$/', $product->barcode);
            $sum = 0;
            for ($i = 0; $i < 13; $i++) {
                $sum += (int) $product->barcode[$i] * ($i % 2 === 0 ? 1 : 3);
            }
            $this->assertSame(0, $sum % 10);
            $movement = $product->inventoryMovements->sole();
            $this->assertSame($admin->id, $movement->user_id);
            $this->assertSame('ENTRADA', $movement->type);
            $this->assertEquals(0, $movement->previous_stock);
            $this->assertEquals($product->current_stock, $movement->new_stock);
            $this->assertEquals($product->current_stock, $movement->quantity);
            if (! $product->unit->allows_decimals) {
                $this->assertEquals(floor((float) $product->current_stock), (float) $product->current_stock);
            }
        }
        $this->actingAs($admin)->getJson('/pos/search-product?search=2000000000015')->assertOk()->assertJsonPath('product.name', 'Martillo de uña 16 oz');
    }

    public function test_rerunning_preserves_existing_products_prices_stock_and_movements(): void
    {
        User::factory()->administrator()->create();
        $unit = Unit::create(['name' => 'Unidad propia', 'abbreviation' => 'up', 'active' => true, 'allows_decimals' => false]);
        $existing = Product::create(['name' => 'Mi producto', 'sku' => 'PROPIO-001', 'unit_id' => $unit->id, 'sale_price' => 777, 'current_stock' => 9, 'active' => true]);
        $this->seed(ProductSeeder::class);
        $demo = Product::where('sku', 'DEMO-FER-001')->firstOrFail();
        $demo->update(['name' => 'Nombre editado', 'sale_price' => 199, 'current_stock' => 7, 'active' => false]);
        $this->seed(ProductSeeder::class);
        $this->assertDatabaseCount('products', 36);
        $this->assertDatabaseCount('inventory_movements', 35);
        $this->assertSame('Nombre editado', $demo->fresh()->name);
        $this->assertEquals(199, $demo->fresh()->sale_price);
        $this->assertEquals(7, $demo->fresh()->current_stock);
        $this->assertFalse($demo->fresh()->active);
        $this->assertSame('Mi producto', $existing->fresh()->name);
        $this->assertEquals(777, $existing->fresh()->sale_price);
        $this->assertEquals(9, $existing->fresh()->current_stock);
    }

    public function test_barcode_conflicts_roll_back_the_entire_catalog(): void
    {
        User::factory()->administrator()->create();
        $unit = Unit::create(['name' => 'Unidad propia', 'abbreviation' => 'up', 'active' => true]);
        Product::create(['name' => 'Código ya utilizado', 'sku' => 'PROPIO', 'barcode' => '2000000000022', 'unit_id' => $unit->id, 'current_stock' => 6]);
        try {
            $this->seed(ProductSeeder::class);
            $this->fail('Se esperaba un conflicto de código de barras.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ya pertenece a otro producto', $exception->getMessage());
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('products', ['sku' => 'PROPIO', 'current_stock' => 6]);
    }

    public function test_no_products_are_created_without_an_administrator(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('administrador');
        $this->seed(ProductSeeder::class);
    }
}
