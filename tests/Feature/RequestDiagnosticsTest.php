<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

class RequestDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.channels.diagnostics' => ['driver' => 'monolog', 'handler' => TestHandler::class, 'level' => 'debug']]);
        Log::forgetChannel('diagnostics');
        $this->logs = Log::channel('diagnostics')->getLogger()->getHandlers()[0];
    }

    public function test_operations_have_server_generated_reference_and_safe_context(): void
    {
        $admin = User::factory()->administrator()->create();
        $response = $this->actingAs($admin)->withHeader('X-Request-ID', 'spoofed')->post('/cash/open', ['opening_amount' => 100, 'notes' => 'private-notes']);
        $response->assertSessionHasNoErrors();
        $id = $response->headers->get('X-Request-ID');
        $this->assertTrue(Str::isUuid($id));
        $record = collect($this->logs->getRecords())->first(fn ($r) => $r->message === 'http.request');
        $this->assertEquals($id, $record->context['request_id']);
        $this->assertEquals($admin->id, $record->context['user_id']);
        $this->assertEquals('cash.open', $record->context['route']);
        $this->assertArrayHasKey('duration_ms', $record->context);
        $this->assertStringNotContainsString('private-notes', json_encode($record->context));
    }

    public function test_exception_is_logged_with_trace_but_without_payload_query_tokens_or_message(): void
    {
        Route::get('/diagnostic-test/{token}', fn () => throw new \RuntimeException('secret-exception-content'))->name('diagnostic.test');
        $response = $this->getJson('/diagnostic-test/private-path?password=private-query', ['Authorization' => 'Bearer private-token']);
        $response->assertStatus(500)->assertJsonPath('request_id', $response->headers->get('X-Request-ID'));
        $records = $this->logs->getRecords();
        $exception = collect($records)->first(fn ($r) => $r->message === 'application.exception');
        $this->assertNotNull($exception);
        $this->assertCount(1, collect($records)->filter(fn ($r) => $r->message === 'application.exception'));
        $this->assertEquals(\RuntimeException::class, $exception->context['exception_class']);
        $this->assertNotEmpty($exception->context['trace']);
        $this->assertEquals('diagnostic-test/{token}', $exception->context['route_pattern']);
        $content = json_encode(array_map(fn ($r) => $r->context, $records)).$response->getContent();
        foreach (['secret-exception-content', 'private-path', 'private-query', 'private-token'] as $secret) {
            $this->assertStringNotContainsString($secret, $content);
        }
        $this->get('/diagnostic-test/another-private-path')->assertStatus(500)->assertSee('Referencia:')->assertDontSee('secret-exception-content');
    }

    public function test_validation_and_permission_failures_are_searchable_by_reference(): void
    {
        $admin = User::factory()->administrator()->create();
        $response = $this->actingAs($admin)->postJson('/cash/open', ['opening_amount' => -1, 'password' => 'private-body']);
        $response->assertUnprocessable()->assertJsonPath('request_id', $response->headers->get('X-Request-ID'));
        $record = collect($this->logs->getRecords())->last();
        $this->assertEquals(422, $record->context['status']);
        $this->assertContains('opening_amount', $record->context['validation_fields']);
        $this->assertStringNotContainsString('private-body', json_encode($record->context));
        $redirect = $this->from('/cash')->post('/cash/open', ['opening_amount' => -1]);
        $id = $redirect->headers->get('X-Request-ID');
        $this->get('/cash')->assertSee($id);
        $this->actingAs(User::factory()->create())->postJson('/customers', ['name' => 'Rejected'])->assertForbidden()->assertJsonStructure(['request_id']);
        $this->assertEquals(403, collect($this->logs->getRecords())->last()->context['status']);
    }

    public function test_caught_inventory_failure_is_logged_and_rolls_back_with_safe_error(): void
    {
        $admin = User::factory()->administrator()->create();
        $unit = Unit::create(['name' => 'Pieza', 'abbreviation' => 'pza', 'allows_decimals' => false, 'active' => true]);
        $product = Product::create(['name' => 'Prueba', 'unit_id' => $unit->id, 'cost' => 1, 'sale_price' => 10, 'tax_percentage' => 0, 'current_stock' => 2, 'minimum_stock' => 0, 'active' => true]);
        Event::listen('eloquent.creating: App\\Models\\InventoryMovement', fn () => throw new \RuntimeException('private-database-detail'));
        $response = $this->actingAs($admin)->from('/inventory/'.$product->id)->post('/inventory/'.$product->id.'/movement', ['type' => 'ENTRADA', 'quantity' => 1, 'reason' => 'Prueba de fallo']);
        $response->assertSessionHasErrors('inventory');
        $this->assertTrue(collect($this->logs->getRecords())->contains(fn ($r) => $r->message === 'application.exception'));
        $this->get('/inventory/'.$product->id)->assertSee($response->headers->get('X-Request-ID'))->assertDontSee('private-database-detail');
        $this->assertEquals(2, $product->fresh()->current_stock);
    }

    public function test_slow_reads_are_logged_and_diagnostic_channel_has_daily_rotation(): void
    {
        $configuration = require config_path('logging.php');
        $this->assertSame('daily', $configuration['channels']['diagnostics']['driver']);
        $this->assertSame(storage_path('logs/diagnostics.log'), $configuration['channels']['diagnostics']['path']);
        config(['logging.diagnostics_slow_ms' => 0]);
        $this->get('/')->assertOk()->assertHeader('X-Request-ID');
        $record = collect($this->logs->getRecords())->last();
        $this->assertTrue($record->context['slow']);
    }
}
