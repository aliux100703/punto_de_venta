<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_asks_to_open_cash_and_declining_enables_only_reading(): void
    {
        $seller = User::factory()->create();
        $this->post('/login', ['email' => $seller->email, 'password' => 'password'])->assertRedirect('/shift/start');
        $this->get('/shift/start')->assertOk()->assertSee('No, entrar solo a consultar');
        $this->post('/shift/decline')->assertRedirect('/dashboard');
        foreach (['/dashboard', '/products', '/inventory', '/customers', '/sales', '/quotes', '/cash', '/profile'] as $url) {
            $this->get($url)->assertOk()->assertSee('Modo consulta');
        }
        foreach (['/pos', '/customers/create', '/quotes/create'] as $url) {
            $this->get($url)->assertRedirect('/cash');
        }
        Customer::create(['name' => 'Existente', 'active' => true]);
        Quote::create(['folio' => 'COT-LECTURA', 'user_id' => $seller->id, 'total' => 10, 'status' => 'PENDIENTE', 'expiration_date' => today()->addDay()]);
        foreach ([['POST', '/customers'], ['POST', '/pos/sale'], ['POST', '/quotes'], ['PATCH', '/quotes/1'], ['POST', '/quotes/1/convert'], ['DELETE', '/customers/1'], ['PATCH', '/profile'], ['PUT', '/password']] as [$method, $url]) {
            $this->call($method, $url)->assertForbidden();
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('cash_registers', 0);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cash_declined', 'user_id' => $seller->id]);
    }

    public function test_opening_cash_enables_card_sales_and_customer_changes(): void
    {
        $seller = User::factory()->create();
        $unit = Unit::create(['name' => 'Pieza', 'abbreviation' => 'pza', 'allows_decimals' => false, 'active' => true]);
        $product = Product::create(['name' => 'Prueba', 'unit_id' => $unit->id, 'cost' => 1, 'sale_price' => 10, 'tax_percentage' => 0, 'current_stock' => 2, 'minimum_stock' => 0, 'active' => true]);
        $card = PaymentMethod::create(['name' => 'Tarjeta', 'active' => true]);
        $this->actingAs($seller)->postJson('/pos/sale', ['payment_method_id' => $card->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertForbidden();
        $this->post('/cash/open', ['opening_amount' => 100])->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertDontSee('Modo consulta');
        $this->postJson('/pos/sale', ['payment_method_id' => $card->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertOk();
        $this->post('/customers', ['name' => 'Cliente permitido', 'active' => 1])->assertSessionHasNoErrors();
    }

    public function test_logout_requires_cash_count_and_confirmation_and_cancel_keeps_session_open(): void
    {
        $seller = User::factory()->create();
        $this->actingAs($seller)->post('/cash/open', ['opening_amount' => 100])->assertSessionHasNoErrors();
        $cash = CashRegister::firstOrFail();
        $this->post('/logout')->assertRedirect('/shift/end');
        $this->assertAuthenticatedAs($seller);
        $this->get('/shift/end')->assertOk()->assertSee('No, continuar en el sistema');
        $this->get('/dashboard')->assertOk();
        $this->assertEquals('ABIERTA', $cash->fresh()->status);
        $this->post('/cash/close', ['counted_cash' => 100])->assertRedirect('/shift/end');
        $this->assertEquals('ABIERTA', $cash->fresh()->status);
        $this->post('/shift/end', ['cash_register_id' => $cash->id, 'counted_cash' => -1])->assertSessionHasErrors('counted_cash');
        $this->assertAuthenticatedAs($seller);
        $this->post('/shift/end', ['cash_register_id' => $cash->id + 1, 'counted_cash' => 100])->assertSessionHasErrors('cash_register_id');
        $this->post('/shift/end', ['cash_register_id' => $cash->id, 'counted_cash' => 95])->assertRedirect('/');
        $this->assertGuest();
        $this->assertEquals('CERRADA', $cash->fresh()->status);
        $this->assertEquals(-5, $cash->fresh()->difference);
        $this->assertDatabaseHas('audit_logs', ['event' => 'logout', 'user_id' => $seller->id]);
    }

    public function test_seller_without_open_cash_can_logout_and_existing_shift_is_not_duplicated(): void
    {
        $seller = User::factory()->create();
        $this->actingAs($seller)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->actingAs($seller)->post('/cash/open', ['opening_amount' => 0])->assertSessionHasNoErrors();
        $this->get('/shift/start')->assertOk()->assertSee('Continuar con mi caja')->assertDontSee('No, entrar solo a consultar');
        $this->post('/cash/open', ['opening_amount' => 0])->assertSessionHasErrors('opening_amount');
        $this->post('/shift/decline')->assertStatus(409);
        $this->assertDatabaseCount('cash_registers', 1);
    }

    public function test_admin_keeps_management_access_without_cash_and_other_cash_does_not_unlock_seller(): void
    {
        $admin = User::factory()->administrator()->create();
        $seller = User::factory()->create();
        $this->actingAs($admin)->post('/customers', ['name' => 'Cliente admin', 'active' => 1])->assertSessionHasNoErrors();
        $this->post('/cash/open', ['opening_amount' => 100])->assertSessionHasNoErrors();
        $this->actingAs($seller)->post('/customers', ['name' => 'No permitido', 'active' => 1])->assertForbidden();
        $this->get('/cash')->assertOk()->assertSee('Modo consulta');
    }
}
