<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $product;

    private PaymentMethod $cash;

    private PaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::factory()->administrator()->create();
        $unit = Unit::create(['name' => 'Pieza', 'abbreviation' => 'pza', 'allows_decimals' => false, 'active' => true]);
        $this->product = Product::create(['name' => 'Martillo', 'sku' => 'M-1', 'unit_id' => $unit->id, 'cost' => 40, 'sale_price' => 100, 'tax_percentage' => 16, 'current_stock' => 10, 'minimum_stock' => 2, 'active' => true]);
        $this->cash = PaymentMethod::create(['name' => 'Efectivo', 'active' => true]);
        $this->card = PaymentMethod::create(['name' => 'Tarjeta', 'active' => true]);
        $this->actingAs($this->cashier);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['payment_method_id' => $this->cash->id, 'cash_received' => 250, 'items' => [['product_id' => $this->product->id, 'quantity' => 2]]], $changes);
    }

    private function open(): CashRegister
    {
        $this->post('/cash/open', ['opening_amount' => 100])->assertSessionHasNoErrors();

        return CashRegister::firstOrFail();
    }

    public function test_cash_sale_updates_stock_payment_cash_and_closure(): void
    {
        $register = $this->open();
        $response = $this->postJson('/pos/sale', $this->payload())->assertOk()->assertJsonPath('sale.total', 232)->assertJsonPath('sale.change', 18);
        $this->assertEquals(8, $this->product->fresh()->current_stock);
        $this->assertDatabaseHas('sale_payments', ['amount' => 232]);
        $this->assertDatabaseHas('inventory_movements', ['type' => 'VENTA', 'previous_stock' => 10, 'new_stock' => 8]);
        $this->assertDatabaseHas('cash_movements', ['cash_register_id' => $register->id, 'amount' => 232, 'type' => 'ENTRADA']);
        $this->get($response->json('sale.url'))->assertOk()->assertSee('Martillo');
        $this->post('/cash/close', ['counted_cash' => 330])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cash_registers', ['id' => $register->id, 'status' => 'CERRADA', 'expected_cash' => 332, 'difference' => -2]);
    }

    public function test_rejected_sales_leave_no_partial_records(): void
    {
        $this->postJson('/pos/sale', $this->payload())->assertUnprocessable();
        $this->open();
        $this->postJson('/pos/sale', $this->payload(['cash_received' => 1]))->assertUnprocessable();
        $this->postJson('/pos/sale', $this->payload(['items' => [['product_id' => $this->product->id, 'quantity' => 11]]]))->assertUnprocessable();
        $this->postJson('/pos/sale', $this->payload(['items' => [['product_id' => $this->product->id, 'quantity' => 0.5]]]))->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_details', 0);
        $this->assertDatabaseCount('cash_movements', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertEquals(10, $this->product->fresh()->current_stock);
    }

    public function test_duplicate_items_and_inactive_methods_are_rejected(): void
    {
        $items = array_fill(0, 2, ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->postJson('/pos/sale', $this->payload(['items' => $items]))->assertUnprocessable();
        $this->cash->update(['active' => false]);
        $this->postJson('/pos/sale', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_card_sale_does_not_require_or_change_cash_register(): void
    {
        $this->postJson('/pos/sale', $this->payload(['payment_method_id' => $this->card->id]))->assertOk()->assertJsonPath('sale.cash_received', null)->assertJsonPath('sale.change', 0);
        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_cash_expenses_reduce_closure_and_cannot_exceed_balance(): void
    {
        $register = $this->open();
        $category = ExpenseCategory::create(['name' => 'Servicios', 'active' => true]);
        $data = ['expense_category_id' => $category->id, 'payment_method_id' => $this->cash->id, 'concept' => 'Transporte', 'amount' => 30, 'expense_date' => today()->toDateString()];
        $this->post('/expenses', $data)->assertSessionHasNoErrors()->assertRedirect('/expenses');
        $this->assertDatabaseHas('cash_movements', ['type' => 'SALIDA', 'amount' => 30, 'reference_type' => 'EXPENSE']);
        $this->post('/expenses', array_replace($data, ['amount' => 80]))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('expenses', 1);
        $this->post('/cash/close', ['counted_cash' => 70])->assertSessionHasNoErrors();
        $this->assertEquals(70, $register->fresh()->expected_cash);
    }

    public function test_quote_preserves_price_does_not_reserve_stock_and_can_only_be_converted_once(): void
    {
        $this->open();
        $this->post('/quotes', ['expiration_date' => today()->addDays(7)->toDateString(), 'items' => [['product_id' => $this->product->id, 'quantity' => 2]]])->assertSessionHasNoErrors();
        $quote = Quote::firstOrFail();
        $this->assertEquals(232, $quote->total);
        $this->assertEquals(10, $this->product->fresh()->current_stock);
        $this->product->update(['sale_price' => 150]);
        $this->post('/quotes/'.$quote->id.'/convert', ['payment_method_id' => $this->cash->id, 'cash_received' => 250])->assertSessionHasNoErrors();
        $this->assertEquals('CONVERTIDA', $quote->fresh()->status);
        $this->assertEquals(232, Sale::firstOrFail()->total);
        $this->assertEquals(8, $this->product->fresh()->current_stock);
        $this->post('/quotes/'.$quote->id.'/convert', ['payment_method_id' => $this->cash->id, 'cash_received' => 250])->assertSessionHasErrors('quote');
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_expired_quote_cannot_be_converted(): void
    {
        $this->post('/quotes', ['expiration_date' => today()->toDateString(), 'items' => [['product_id' => $this->product->id, 'quantity' => 1]]]);
        $quote = Quote::firstOrFail();
        $quote->update(['expiration_date' => today()->subDay()]);
        $this->post('/quotes/'.$quote->id.'/convert', ['payment_method_id' => $this->card->id])->assertSessionHasErrors('quote');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_cash_registers_are_isolated_by_user_and_cannot_open_twice(): void
    {
        $this->open();
        $this->post('/cash/open', ['opening_amount' => 20])->assertSessionHasErrors('opening_amount');
        $this->actingAs(User::factory()->create());
        $this->postJson('/pos/sale', $this->payload())->assertForbidden();
        $this->post('/cash/close', ['counted_cash' => 100])->assertRedirect('/shift/end');
        $this->assertDatabaseCount('cash_registers', 1);
    }

    public function test_customer_can_be_created_updated_and_selected_on_sale(): void
    {
        $this->post('/customers', ['name' => 'Ana', 'email' => 'ana@example.com', 'active' => 1])->assertSessionHasNoErrors();
        $customer = Customer::firstOrFail();
        $this->put('/customers/'.$customer->id, ['name' => 'Ana López', 'active' => 1])->assertSessionHasNoErrors();
        $this->postJson('/pos/sale', $this->payload(['payment_method_id' => $this->card->id, 'customer_id' => $customer->id]))->assertOk();
        $this->assertDatabaseHas('sales', ['customer_id' => $customer->id]);
        $this->delete('/customers/'.$customer->id)->assertSessionHasNoErrors();
        $this->assertFalse($customer->fresh()->active);
    }

    public function test_all_operational_pages_render_and_guests_are_redirected(): void
    {
        foreach (['/dashboard', '/pos', '/products', '/products/create', '/inventory', '/cash', '/customers', '/customers/create', '/sales', '/quotes', '/quotes/create', '/expenses'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->post('/quotes', ['expiration_date' => today()->toDateString(), 'items' => [['product_id' => $this->product->id, 'quantity' => 1]]]);
        $this->get('/quotes/'.Quote::firstOrFail()->id)->assertOk();
        auth()->logout();
        foreach (['/dashboard', '/pos', '/cash', '/customers', '/sales', '/quotes', '/expenses'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_product_search_prefers_exact_barcode_over_partial_name(): void
    {
        Product::create(['name' => 'M-1 accesorio', 'unit_id' => $this->product->unit_id, 'sale_price' => 5, 'active' => true]);
        $this->getJson('/pos/search-product?search=M-1')->assertOk()->assertJsonPath('product.id', $this->product->id);
    }

    public function test_initial_catalog_seeding_is_repeatable_and_preserves_disabled_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->cash->update(['active' => false]);
        $count = PaymentMethod::count();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($count, PaymentMethod::count());
        $this->assertFalse($this->cash->fresh()->active);
    }

    public function test_catalogs_can_be_managed_and_unknown_catalogs_are_rejected(): void
    {
        foreach (['categories', 'brands', 'units', 'expense-categories'] as $type) {
            $this->get('/catalogs/'.$type)->assertOk();
        }
        $this->post('/catalogs/brands', ['name' => 'Truper'])->assertSessionHasNoErrors();
        $brand = Brand::firstOrFail();
        $this->patch('/catalogs/brands/'.$brand->id, ['active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($brand->fresh()->active);
        $this->get('/catalogs/users')->assertNotFound();
        $this->post('/catalogs/users', ['name' => 'Invalid'])->assertNotFound();
    }

    public function test_inventory_rejects_negative_stock_fractional_pieces_and_excess_precision(): void
    {
        foreach ([['type' => 'MERMA', 'quantity' => 11], ['type' => 'ENTRADA', 'quantity' => 0.5], ['type' => 'ENTRADA', 'quantity' => 0.0001]] as $data) {
            $this->post('/inventory/'.$this->product->id.'/movement', $data + ['reason' => 'Ajuste'])->assertSessionHasErrors();
        }
        $this->assertEquals(10, $this->product->fresh()->current_stock);
        $this->post('/inventory/'.$this->product->id.'/movement', ['type' => 'ENTRADA', 'quantity' => 3, 'reason' => 'Recepción'])->assertSessionHasNoErrors();
        $this->assertEquals(13, $this->product->fresh()->current_stock);
    }

    public function test_fractional_stock_and_per_line_rounding_match_saved_totals(): void
    {
        $this->product->unit->update(['allows_decimals' => true]);
        $this->product->update(['sale_price' => 0.99]);
        $this->postJson('/pos/sale', $this->payload(['payment_method_id' => $this->card->id, 'items' => [['product_id' => $this->product->id, 'quantity' => 0.333]]]))
            ->assertOk()->assertJsonPath('sale.subtotal', 0.33)->assertJsonPath('sale.taxes', 0.05)->assertJsonPath('sale.total', 0.38);
        $this->assertEquals(9.667, $this->product->fresh()->current_stock);
    }

    public function test_accounts_with_operations_cannot_be_deleted(): void
    {
        $this->open();
        $this->delete('/profile', ['password' => 'password'])->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($this->cashier->fresh());
        $this->assertAuthenticated();
    }

    public function test_sales_date_filters_work_independently_and_reject_reversed_ranges(): void
    {
        $this->get('/sales?to='.today()->toDateString())->assertOk();
        $this->get('/sales?from='.today()->toDateString())->assertOk();
        $this->get('/sales?from='.today()->toDateString().'&to='.today()->subDay()->toDateString())->assertSessionHasErrors('to');
    }
}
