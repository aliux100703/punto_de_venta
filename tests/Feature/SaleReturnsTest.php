<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\InventoryMovement;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleReturnsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private PaymentMethod $cash;

    private PaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrator()->create();
        $unit = Unit::create(['name' => 'Pieza', 'abbreviation' => 'pza', 'allows_decimals' => false, 'active' => true]);
        $this->product = Product::create(['name' => 'Martillo de prueba', 'unit_id' => $unit->id, 'sale_price' => 100, 'cost' => 50, 'tax_percentage' => 16, 'current_stock' => 10, 'minimum_stock' => 2, 'active' => true]);
        $this->cash = PaymentMethod::create(['name' => 'Efectivo', 'active' => true]);
        $this->card = PaymentMethod::create(['name' => 'Tarjeta', 'active' => true]);
        $this->actingAs($this->admin);
    }

    private function sale(float $quantity = 2, bool $cash = true, ?User $seller = null): Sale
    {
        $seller ??= $this->admin;
        $this->actingAs($seller);
        if ($cash && ! CashRegister::where('user_id', $seller->id)->where('status', 'ABIERTA')->exists()) {
            $this->post('/cash/open', ['opening_amount' => 100])->assertSessionHasNoErrors();
        }
        $response = $this->postJson('/pos/sale', ['payment_method_id' => $cash ? $this->cash->id : $this->card->id, 'cash_received' => 1000, 'items' => [['product_id' => $this->product->id, 'quantity' => $quantity]]])->assertOk();
        $this->actingAs($this->admin);

        return Sale::findOrFail($response->json('sale.id'));
    }

    private function payload(): array
    {
        return ['request_key' => (string) Str::uuid(), 'reason' => 'Ajuste solicitado por el cliente', 'external_reference' => 'REF-TEST-001', 'refund_confirmed' => 1];
    }

    private function returnData(Sale $sale, float $quantity = 1, bool $restock = true): array
    {
        return $this->payload() + ['items' => [['sale_detail_id' => $sale->details()->firstOrFail()->id, 'quantity' => $quantity, 'restock' => $restock]]];
    }

    public function test_full_cash_cancellation_restores_stock_and_records_refund_without_editing_original_payment(): void
    {
        $sale = $this->sale();
        $register = CashRegister::firstOrFail();
        $payment = $sale->payments()->firstOrFail()->getRawOriginal();
        $originalDate = $sale->created_at->toDateTimeString();
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertRedirect('/sales/'.$sale->id)->assertSessionHasNoErrors();
        $adjustment = SaleReturn::firstOrFail();
        $this->assertEquals('CANCELADA', $sale->fresh()->status);
        $this->assertEquals(232, $sale->fresh()->total);
        $this->assertEquals(232, $sale->fresh()->refunded_amount);
        $this->assertEquals(0, $sale->fresh()->net_total);
        $this->assertSame($originalDate, $sale->fresh()->created_at->toDateTimeString());
        $this->assertSame($payment, $sale->payments()->firstOrFail()->getRawOriginal());
        $this->assertEquals(10, $this->product->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_movements', ['type' => 'DEVOLUCION', 'reference_id' => $adjustment->id, 'quantity' => 2, 'previous_stock' => 8, 'new_stock' => 10]);
        $this->assertDatabaseHas('cash_movements', ['type' => 'SALIDA', 'amount' => 232, 'cash_register_id' => $register->id, 'reference_type' => 'SALE_RETURN']);
        $this->get('/sale-returns')->assertOk()->assertSee($adjustment->folio);
        $this->get('/sales/'.$sale->id.'/returns/'.$adjustment->id)->assertOk()->assertSee('Martillo de prueba');
        $this->post('/cash/close', ['counted_cash' => 100])->assertSessionHasNoErrors();
        $this->assertEquals(100, $register->fresh()->expected_cash);
    }

    public function test_successive_partial_returns_reach_full_return_and_only_refund_remaining_quantities(): void
    {
        $sale = $this->sale(3);
        $first = $this->returnData($sale, 1);
        $this->post('/sales/'.$sale->id.'/returns', $first)->assertSessionHasNoErrors();
        $this->assertEquals('DEVOLUCION_PARCIAL', $sale->fresh()->status);
        $this->assertEquals(116, $sale->fresh()->refunded_amount);
        $this->assertEquals(232, $sale->fresh()->net_total);
        $this->assertEquals(8, $this->product->fresh()->current_stock);
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale, 2))->assertSessionHasNoErrors();
        $this->assertEquals('DEVUELTA', $sale->fresh()->status);
        $this->assertEquals(348, $sale->fresh()->refunded_amount);
        $this->assertEquals(10, $this->product->fresh()->current_stock);
        $this->assertEquals(48, SaleReturn::sum('taxes'));
        $this->assertEquals(348, SaleReturn::sum('total'));
        $this->post('/sales/'.$sale->id.'/returns', $first)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sale_returns', 2);
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale, 1))->assertSessionHasErrors('sale');
    }

    public function test_same_cancellation_request_is_idempotent_and_a_different_request_is_rejected(): void
    {
        $sale = $this->sale();
        $data = $this->payload();
        $this->post('/sales/'.$sale->id.'/cancel', $data)->assertSessionHasNoErrors();
        $this->post('/sales/'.$sale->id.'/cancel', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sale_returns', 1);
        $this->assertEquals(1, CashMovement::where('reference_type', 'SALE_RETURN')->count());
        $this->assertEquals(1, InventoryMovement::where('type', 'DEVOLUCION')->count());
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasErrors('sale');
    }

    public function test_a_request_key_cannot_be_reused_for_different_data(): void
    {
        $sale = $this->sale(3);
        $data = $this->returnData($sale);
        $this->post('/sales/'.$sale->id.'/returns', $data)->assertSessionHasNoErrors();
        $data['items'][0]['quantity'] = 2;
        $this->post('/sales/'.$sale->id.'/returns', $data)->assertSessionHasErrors('request_key');
        $this->assertDatabaseCount('sale_returns', 1);
        $this->assertEquals(116, $sale->fresh()->refunded_amount);
    }

    public function test_returns_require_an_open_cash_register_with_sufficient_balance(): void
    {
        $sale = $this->sale();
        $register = CashRegister::firstOrFail();
        $register->movements()->create(['user_id' => $this->admin->id, 'type' => 'SALIDA', 'concept' => 'Retiro de prueba', 'amount' => 300]);
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasErrors('cash');
        $this->assertDatabaseCount('sale_returns', 0);
        $this->assertEquals('COMPLETADA', $sale->fresh()->status);
        $this->assertEquals(8, $this->product->fresh()->current_stock);
        $this->assertEquals(0, $sale->details()->firstOrFail()->returned_quantity);
    }

    public function test_refund_from_later_shift_does_not_change_the_original_closed_register(): void
    {
        $sale = $this->sale();
        $original = CashRegister::firstOrFail();
        $this->post('/cash/close', ['counted_cash' => 332])->assertSessionHasNoErrors();
        $closed = $original->fresh()->getRawOriginal();
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasErrors('cash');
        $this->post('/cash/open', ['opening_amount' => 250])->assertSessionHasNoErrors();
        $new = CashRegister::where('status', 'ABIERTA')->firstOrFail();
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasNoErrors();
        $this->assertSame($closed, $original->fresh()->getRawOriginal());
        $this->assertEquals($new->id, SaleReturn::firstOrFail()->cash_register_id);
        $this->post('/cash/close', ['counted_cash' => 18])->assertSessionHasNoErrors();
        $this->assertEquals(18, $new->fresh()->expected_cash);
    }

    public function test_admin_refunds_a_sellers_sale_from_admins_own_register(): void
    {
        $seller = User::factory()->create();
        $sale = $this->sale(2, true, $seller);
        $sellerRegister = CashRegister::where('user_id', $seller->id)->firstOrFail();
        $this->post('/cash/open', ['opening_amount' => 300])->assertSessionHasNoErrors();
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasNoErrors();
        $adjustment = SaleReturn::firstOrFail();
        $this->assertEquals($this->admin->id, $adjustment->user_id);
        $this->assertNotEquals($sellerRegister->id, $adjustment->cash_register_id);
        $this->assertEquals(0, $sellerRegister->movements()->where('type', 'SALIDA')->count());
    }

    public function test_card_and_transfer_refunds_are_blocked_even_with_external_confirmation(): void
    {
        foreach (['Tarjeta', 'Transferencia'] as $name) {
            $sale = $this->sale(2, false);
            $sale->payments()->firstOrFail()->update(['method_name' => $name]);
            $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale))->assertSessionHasErrors('sale');
            $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasErrors('sale');
            $this->get('/sales/'.$sale->id)->assertOk()->assertDontSee('id="returnForm"', false)->assertSee('72 horas')->assertSee('No hay devoluciones');
        }
        $this->assertDatabaseCount('sale_returns', 0);
        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_refund_uses_original_prices_and_cash_snapshot_after_method_rename(): void
    {
        $sale = $this->sale();
        $this->product->update(['sale_price' => 999, 'tax_percentage' => 0, 'active' => false]);
        $this->cash->update(['name' => 'Pago renombrado', 'active' => false]);
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sale_returns', ['total' => 116, 'taxes' => 16, 'payment_method_name' => 'Efectivo', 'is_cash' => true]);
    }

    public function test_refunds_allow_exactly_72_hours_but_block_one_second_later(): void
    {
        $sale = $this->sale();
        $this->travelTo($sale->created_at->copy()->addHours(72));
        $data = $this->returnData($sale);
        $this->post('/sales/'.$sale->id.'/returns', $data)->assertSessionHasNoErrors();
        $this->travel(1)->seconds();
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale))->assertSessionHasErrors('sale');
        $this->post('/sales/'.$sale->id.'/returns', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sale_returns', 1);
        $this->assertEquals(116, $sale->fresh()->refunded_amount);
        $this->get('/sales/'.$sale->id)->assertSee('72 horas')->assertDontSee('id="returnForm"', false);
        $this->travelBack();
    }

    public function test_expired_and_future_sales_cannot_be_cancelled_or_returned(): void
    {
        foreach ([now()->subHours(72)->subSecond(), now()->addDay()] as $date) {
            $sale = $this->sale();
            $sale->forceFill(['created_at' => $date])->save();
            $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasErrors('sale');
            $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale))->assertSessionHasErrors('sale');
        }
        $this->assertDatabaseCount('sale_returns', 0);
        $this->assertEquals(0, CashMovement::where('reference_type', 'SALE_RETURN')->count());
    }

    public function test_excess_negative_fractional_and_duplicate_quantities_are_rejected(): void
    {
        $sale = $this->sale();
        foreach ([3, -1, 0.5, 0.0001] as $quantity) {
            $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale, $quantity))->assertSessionHasErrors();
        }
        $data = $this->returnData($sale);
        $data['items'][] = $data['items'][0];
        $this->post('/sales/'.$sale->id.'/returns', $data)->assertSessionHasErrors();
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale, 0))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sale_returns', 0);
        $this->assertEquals(8, $this->product->fresh()->current_stock);
    }

    public function test_items_from_another_sale_and_cancellation_after_a_partial_return_are_rejected(): void
    {
        $sale = $this->sale();
        $other = $this->sale();
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($other))->assertSessionHasErrors('items');
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale))->assertSessionHasNoErrors();
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasErrors('sale');
        $this->assertDatabaseCount('sale_returns', 1);
        $this->assertEquals('COMPLETADA', $other->fresh()->status);
    }

    public function test_damaged_goods_can_be_refunded_without_restocking(): void
    {
        $sale = $this->sale();
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale, 1, false))->assertSessionHasNoErrors();
        $this->assertEquals(8, $this->product->fresh()->current_stock);
        $this->assertEquals(0, InventoryMovement::where('type', 'DEVOLUCION')->count());
        $this->assertDatabaseHas('sale_return_items', ['quantity' => 1, 'restock' => false, 'total' => 116]);
        $this->assertEquals(1, $sale->details()->firstOrFail()->returned_quantity);
    }

    public function test_fractional_returns_allocate_cents_exactly_over_multiple_requests(): void
    {
        $this->product->unit->update(['allows_decimals' => true]);
        $this->product->update(['sale_price' => 0.01, 'tax_percentage' => 0]);
        $sale = $this->sale(1);
        foreach ([0.333, 0.333, 0.334] as $quantity) {
            $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale, $quantity))->assertSessionHasNoErrors();
        }
        $this->assertEquals(0.01, SaleReturn::sum('total'));
        $this->assertEquals(0.01, $sale->fresh()->refunded_amount);
        $this->assertEquals('DEVUELTA', $sale->fresh()->status);
        $this->assertEquals(10, $this->product->fresh()->current_stock);
    }

    public function test_tax_and_rounding_remainders_are_exact_on_final_return(): void
    {
        $this->product->update(['sale_price' => 0.05]);
        $sale = $this->sale(3);
        foreach (range(1, 3) as $iteration) {
            $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale))->assertSessionHasNoErrors();
        }
        $this->assertSame(17, SaleReturn::all()->sum(fn ($row) => (int) round((float) $row->total * 100)));
        $this->assertSame(2, SaleReturn::all()->sum(fn ($row) => (int) round((float) $row->taxes * 100)));
        $this->assertSame(15, SaleReturn::all()->sum(fn ($row) => (int) round((float) $row->subtotal * 100)));
        $this->assertEquals(0, $sale->fresh()->net_total);
    }

    public function test_zero_value_cash_sale_can_be_returned_without_opening_another_register(): void
    {
        $this->product->update(['sale_price' => 0]);
        $sale = $this->sale(1);
        $this->post('/cash/close', ['counted_cash' => 100])->assertSessionHasNoErrors();
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasNoErrors();
        $this->assertEquals(0, CashMovement::where('reference_type', 'SALE_RETURN')->count());
        $this->assertEquals(10, $this->product->fresh()->current_stock);
    }

    public function test_sellers_cannot_process_adjustments_but_can_read_their_own_receipts(): void
    {
        $seller = User::factory()->create();
        $sale = $this->sale(2, true, $seller);
        $this->post('/cash/open', ['opening_amount' => 300])->assertSessionHasNoErrors();
        $this->actingAs($seller)->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertForbidden();
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale))->assertForbidden();
        $this->get('/sale-returns')->assertForbidden();
        $this->get('/sales/'.$sale->id)->assertDontSee('Cancelar venta completa')->assertDontSee('Registrar devolución');
        $this->actingAs($this->admin)->post('/sales/'.$sale->id.'/returns', $this->returnData($sale))->assertSessionHasNoErrors();
        $adjustment = SaleReturn::firstOrFail();
        $this->actingAs($seller)->get('/sales/'.$sale->id.'/returns/'.$adjustment->id)->assertOk();
        $this->actingAs(User::factory()->create())->get('/sales/'.$sale->id.'/returns/'.$adjustment->id)->assertForbidden();
        $this->actingAs($this->admin);
        $other = $this->sale(1, false);
        $this->get('/sales/'.$other->id.'/returns/'.$adjustment->id)->assertNotFound();
    }

    public function test_net_reports_keep_partial_sales_and_exclude_fully_cancelled_sales(): void
    {
        $sale = $this->sale();
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale))->assertSessionHasNoErrors();
        $this->get('/sales')->assertOk()->assertViewHas('total', 116)->assertViewHas('refundedTotal', 116);
        $this->get('/dashboard')->assertOk()->assertViewHas('revenue', 116.0)->assertViewHas('periodRevenue', 116.0);
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale))->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertOk()->assertViewHas('revenue', 0.0)->assertViewHas('periodRevenue', 0.0);
        $this->get('/sales')->assertViewHas('total', 0);
    }

    public function test_original_quantity_rules_are_preserved_if_product_unit_changes(): void
    {
        $sale = $this->sale();
        $this->product->unit->update(['allows_decimals' => true]);
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale, 0.5))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sale_returns', 0);
    }

    public function test_mixed_or_inconsistent_payments_require_reconciliation(): void
    {
        $sale = $this->sale(2, false);
        $sale->payments()->firstOrFail()->update(['amount' => 116]);
        $sale->payments()->create(['payment_method_id' => $this->cash->id, 'amount' => 116]);
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasErrors('sale');
        $this->assertDatabaseCount('sale_returns', 0);
    }

    public function test_a_converted_quote_stays_converted_after_its_sale_is_cancelled(): void
    {
        $this->post('/quotes', ['expiration_date' => today()->addDay()->toDateString(), 'items' => [['product_id' => $this->product->id, 'quantity' => 1]]])->assertSessionHasNoErrors();
        $quote = Quote::firstOrFail();
        $this->post('/cash/open', ['opening_amount' => 100])->assertSessionHasNoErrors();
        $this->post('/quotes/'.$quote->id.'/convert', ['payment_method_id' => $this->cash->id, 'cash_received' => 200])->assertSessionHasNoErrors();
        $sale = Sale::findOrFail($quote->fresh()->sale_id);
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasNoErrors();
        $this->assertEquals('CONVERTIDA', $quote->fresh()->status);
        $this->post('/quotes/'.$quote->id.'/convert', ['payment_method_id' => $this->card->id])->assertSessionHasErrors('quote');
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_a_refund_operators_account_cannot_be_deleted(): void
    {
        $seller = User::factory()->create();
        $sale = $this->sale(2, true, $seller);
        $this->post('/cash/open', ['opening_amount' => 300])->assertSessionHasNoErrors();
        User::factory()->administrator()->create();
        $this->post('/sales/'.$sale->id.'/returns', $this->returnData($sale, 1, false))->assertSessionHasNoErrors();
        $this->delete('/profile', ['password' => 'password'])->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($this->admin->fresh());
    }

    public function test_stock_limit_failure_rolls_back_every_previously_processed_item(): void
    {
        $other = Product::create(['name' => 'Segundo producto', 'unit_id' => $this->product->unit_id, 'sale_price' => 100, 'tax_percentage' => 16, 'current_stock' => 10, 'active' => true]);
        $this->post('/cash/open', ['opening_amount' => 100])->assertSessionHasNoErrors();
        $response = $this->postJson('/pos/sale', ['payment_method_id' => $this->cash->id, 'cash_received' => 1000, 'items' => [['product_id' => $this->product->id, 'quantity' => 2], ['product_id' => $other->id, 'quantity' => 2]]])->assertOk();
        $sale = Sale::findOrFail($response->json('sale.id'));
        $other->update(['current_stock' => 999999999]);
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sale_returns', 0);
        $this->assertDatabaseCount('sale_return_items', 0);
        $this->assertEquals(8, $this->product->fresh()->current_stock);
        $this->assertEquals(999999999, $other->fresh()->current_stock);
        $this->assertEquals(0, $sale->details()->sum('returned_quantity'));
        $this->assertEquals(0, InventoryMovement::where('type', 'DEVOLUCION')->count());
        $this->assertEquals(0, CashMovement::where('reference_type', 'SALE_RETURN')->count());
        $this->assertEquals('COMPLETADA', $sale->fresh()->status);
    }

    public function test_role_is_rechecked_before_mutating_financial_records(): void
    {
        $sale = $this->sale();
        $this->admin->load('role');
        $seller = User::factory()->create();
        DB::table('users')->where('id', $this->admin->id)->update(['role_id' => $seller->role_id]);
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('sale_returns', 0);
    }

    public function test_migration_backfills_legacy_payment_and_unit_snapshots_without_changing_sales(): void
    {
        $sale = $this->sale();
        $this->cash->update(['name' => 'Pago de caja renombrado']);
        $migration = require database_path('migrations/2026_10_16_000002_create_sale_returns_tables.php');
        $migration->down();
        $migration->up();
        $this->assertTrue($sale->payments()->firstOrFail()->is_cash);
        $this->assertFalse($sale->details()->firstOrFail()->allows_decimals);
        $this->assertEquals(232, $sale->fresh()->total);
        $this->assertEquals(0, $sale->fresh()->refunded_amount);
        $this->post('/sales/'.$sale->id.'/cancel', $this->payload())->assertSessionHasNoErrors();
        $this->assertTrue(SaleReturn::firstOrFail()->is_cash);
    }
}
