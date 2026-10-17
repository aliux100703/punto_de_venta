<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private User $other;

    private Product $product;

    private PaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrator()->create();
        $this->seller = User::factory()->create();
        $this->other = User::factory()->create();
        $unit = Unit::create(['name' => 'Pieza', 'abbreviation' => 'pza', 'allows_decimals' => false, 'active' => true]);
        $this->product = Product::create(['name' => 'Producto de prueba', 'unit_id' => $unit->id, 'cost' => 41.25, 'sale_price' => 100, 'tax_percentage' => 16, 'current_stock' => 10, 'active' => true]);
        $this->card = PaymentMethod::create(['name' => 'Tarjeta', 'active' => true]);
    }

    private function sale(User $user, string $folio): Sale
    {
        return Sale::create(['folio' => $folio, 'seller_id' => $user->id, 'subtotal' => 100, 'taxes' => 16, 'total' => 116, 'status' => 'COMPLETADA']);
    }

    private function quote(User $user, string $folio): Quote
    {
        return Quote::create(['folio' => $folio, 'user_id' => $user->id, 'expiration_date' => today()->addDay(), 'total' => 116, 'status' => 'PENDIENTE']);
    }

    public function test_seller_can_use_operational_pages_but_cannot_access_administration(): void
    {
        $this->actingAs($this->seller);
        $this->post('/cash/open', ['opening_amount' => 50])->assertSessionHasNoErrors();
        foreach (['/dashboard', '/pos', '/cash', '/products', '/products/'.$this->product->id, '/inventory', '/inventory/'.$this->product->id, '/customers', '/customers/create', '/sales', '/quotes', '/quotes/create'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/users', '/catalogs', '/catalogs/brands', '/expenses', '/products/create', '/products/'.$this->product->id.'/edit', '/inventory/'.$this->product->id.'/movement'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_hidden_buttons_are_also_protected_against_direct_requests(): void
    {
        $this->actingAs($this->seller);
        $this->post('/products', ['name' => 'Intrusión'])->assertForbidden();
        $this->put('/products/'.$this->product->id, ['sale_price' => 1])->assertForbidden();
        $this->delete('/products/'.$this->product->id)->assertForbidden();
        $this->post('/inventory/'.$this->product->id.'/movement', ['type' => 'ENTRADA', 'quantity' => 10, 'reason' => 'Sin permiso'])->assertForbidden();
        $this->post('/expenses', ['amount' => 10])->assertForbidden();
        $this->post('/catalogs/brands', ['name' => 'Sin permiso'])->assertForbidden();
        $this->patch('/catalogs/brands/1', ['active' => 0])->assertForbidden();
        $this->post('/users', ['name' => 'Intrusión'])->assertForbidden();
        $this->patch('/users/'.$this->seller->id, ['role_id' => $this->admin->role_id])->assertForbidden();
        $this->assertEquals(100, $this->product->fresh()->sale_price);
        $this->assertEquals(10, $this->product->fresh()->current_stock);
        $this->assertTrue($this->product->fresh()->active);
        $this->assertFalse($this->seller->fresh()->isAdministrator());
        $this->assertDatabaseCount('users', 3);
    }

    public function test_seller_can_charge_sales_and_create_customers(): void
    {
        $this->actingAs($this->seller);
        $this->post('/cash/open', ['opening_amount' => 50])->assertSessionHasNoErrors();
        $response = $this->postJson('/pos/sale', ['payment_method_id' => $this->card->id, 'items' => [['product_id' => $this->product->id, 'quantity' => 1]]])->assertOk();
        $this->get($response->json('sale.url'))->assertOk();
        $this->assertDatabaseHas('sales', ['seller_id' => $this->seller->id, 'total' => 116]);
        $this->post('/customers', ['name' => 'Cliente del vendedor', 'active' => 1])->assertSessionHasNoErrors();
    }

    public function test_seller_sees_only_own_sales_and_cannot_open_another_receipt(): void
    {
        $mine = $this->sale($this->seller, 'VENTA-PROPIA');
        $other = $this->sale($this->other, 'VENTA-AJENA');
        $this->actingAs($this->seller)->get('/sales')->assertOk()->assertSee($mine->folio)->assertDontSee($other->folio);
        $this->get('/sales/'.$other->id)->assertForbidden();
        $this->actingAs($this->admin)->get('/sales')->assertSee($mine->folio)->assertSee($other->folio);
        $this->get('/sales/'.$other->id)->assertOk();
    }

    public function test_seller_cannot_read_change_or_charge_another_users_quote(): void
    {
        $mine = $this->quote($this->seller, 'COTIZACION-PROPIA');
        $other = $this->quote($this->other, 'COTIZACION-AJENA');
        $this->actingAs($this->seller)->get('/quotes')->assertSee($mine->folio)->assertDontSee($other->folio);
        $this->get('/quotes/'.$mine->id)->assertOk();
        $this->get('/quotes/'.$other->id)->assertForbidden();
        $this->patch('/quotes/'.$other->id, ['status' => 'RECHAZADA'])->assertForbidden();
        $this->post('/quotes/'.$other->id.'/convert', ['payment_method_id' => $this->card->id])->assertForbidden();
        $this->assertEquals('PENDIENTE', $other->fresh()->status);
        $this->assertDatabaseCount('sales', 0);
        $this->actingAs($this->admin)->get('/quotes/'.$other->id)->assertOk();
        $this->patch('/quotes/'.$other->id, ['status' => 'ACEPTADA'])->assertSessionHasNoErrors();
    }

    public function test_dashboard_menu_and_product_details_do_not_expose_admin_data_to_seller(): void
    {
        $mine = $this->sale($this->seller, 'VENTA-EN-MI-PANEL');
        $other = $this->sale($this->other, 'VENTA-FUERA-DE-MI-PANEL');
        $this->actingAs($this->seller)->get('/dashboard')->assertSee($mine->folio)->assertDontSee($other->folio)
            ->assertDontSee(route('users.index'), false)->assertDontSee(route('expenses.index'), false)->assertDontSee(route('catalogs.index'), false)->assertDontSee('Gastos de hoy');
        $this->get('/products')->assertDontSee('Nuevo producto')->assertDontSee(route('products.edit', $this->product), false);
        $this->get('/products/'.$this->product->id)->assertDontSee('Costo')->assertDontSee('41.25');
        $this->get('/inventory')->assertDontSee(route('inventory.movement', $this->product), false);
        $this->get('/inventory/'.$this->product->id)->assertDontSee('Nuevo movimiento');
        $this->actingAs($this->admin)->get('/dashboard')->assertSee($mine->folio)->assertSee($other->folio)->assertSee(route('users.index'), false);
        $this->get('/products/'.$this->product->id)->assertSee('Costo')->assertSee('41.25');
    }

    public function test_administrator_can_create_users_with_hashed_password_and_change_roles(): void
    {
        $this->actingAs($this->admin)->get('/users')->assertOk();
        $this->post('/users', ['name' => 'Nueva vendedora', 'email' => 'vendedora@example.test', 'password' => 'Clave-segura-2026', 'password_confirmation' => 'Clave-segura-2026', 'role_id' => $this->seller->role_id])->assertRedirect('/users')->assertSessionHasNoErrors();
        $user = User::where('email', 'vendedora@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::SELLER));
        $this->assertTrue(Hash::check('Clave-segura-2026', $user->password));
        $this->patch('/users/'.$user->id, ['role_id' => $this->admin->role_id])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->isAdministrator());
        $this->patch('/users/'.$user->id, ['role_id' => $this->seller->role_id])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->isAdministrator());
    }

    public function test_last_administrator_cannot_be_demoted_or_deleted(): void
    {
        $this->actingAs($this->admin)->patch('/users/'.$this->admin->id, ['role_id' => $this->seller->role_id])->assertSessionHasErrors('role_id');
        $this->delete('/profile', ['password' => 'password'])->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertTrue($this->admin->fresh()->isAdministrator());
        $this->assertAuthenticated();
    }

    public function test_administrator_can_demote_self_when_another_administrator_exists(): void
    {
        User::factory()->administrator()->create();
        $this->actingAs($this->admin)->patch('/users/'.$this->admin->id, ['role_id' => $this->seller->role_id])->assertSessionHasNoErrors()->assertRedirect('/dashboard');
        $this->assertFalse($this->admin->fresh()->isAdministrator());
        $this->actingAs($this->admin->fresh())->get('/users')->assertForbidden();
    }

    public function test_public_registration_is_closed_after_setup_and_profile_cannot_escalate_role(): void
    {
        $this->get('/register')->assertForbidden();
        $this->post('/register', ['name' => 'Intrusión', 'email' => 'intrusion@example.test', 'password' => 'Clave-2026', 'password_confirmation' => 'Clave-2026', 'role_id' => $this->admin->role_id])->assertForbidden();
        $this->actingAs($this->seller)->patch('/profile', ['name' => $this->seller->name, 'email' => $this->seller->email, 'role_id' => $this->admin->role_id])->assertSessionHasNoErrors();
        $this->assertFalse($this->seller->fresh()->isAdministrator());
        $this->seller->fill(['role_id' => $this->admin->role_id])->save();
        $this->assertFalse($this->seller->fresh()->isAdministrator());
    }

    public function test_users_without_a_supported_role_are_denied_and_guests_must_sign_in(): void
    {
        $this->seller->forceFill(['role_id' => null])->save();
        $this->actingAs($this->seller)->get('/pos')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/profile')->assertOk();
        auth()->logout();
        $this->get('/users')->assertRedirect('/login');
        $this->postJson('/pos/sale', [])->assertUnauthorized();
    }

    public function test_administrator_can_access_all_management_modules(): void
    {
        $this->actingAs($this->admin);
        foreach (['/users', '/expenses', '/catalogs', '/products/create', '/products/'.$this->product->id.'/edit', '/inventory/'.$this->product->id.'/movement'] as $url) {
            $this->get($url)->assertOk();
        }
        $unknown = Role::create(['name' => 'Sin permisos definidos']);
        $this->patch('/users/'.$this->seller->id, ['role_id' => $unknown->id])->assertSessionHasErrors('role_id');
    }
}
