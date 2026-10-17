<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_failure_success_and_logout_are_recorded_without_secrets(): void
    {
        $user = User::factory()->administrator()->create();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->withHeader('User-Agent', 'Audit test browser');
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-wrong-password'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_failed', 'user_id' => $user->id, 'ip_address' => '192.0.2.10']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->post('/logout')->assertRedirect('/');
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'actor_name' => $user->name]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'logout', 'user_id' => $user->id]);
        $serialized = AuditLog::all()->toJson();
        $this->assertStringNotContainsString('secret-wrong-password', $serialized);
        $this->assertStringNotContainsString($user->getRawOriginal('password'), $serialized);
        $this->assertStringNotContainsString('remember_token', $serialized);
    }

    public function test_unknown_account_and_rate_limit_are_recorded_without_claiming_an_actor(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'private-password']);
        }
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_failed', 'user_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_lockout', 'user_id' => null]);
        $this->assertStringNotContainsString('private-password', AuditLog::all()->toJson());
    }

    public function test_price_role_and_password_changes_capture_only_safe_differences(): void
    {
        $admin = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $unit = Unit::create(['name' => 'Pieza', 'abbreviation' => 'pza', 'allows_decimals' => false, 'active' => true]);
        $product = Product::create(['name' => 'Producto', 'unit_id' => $unit->id, 'cost' => 10, 'sale_price' => 20, 'current_stock' => 0, 'minimum_stock' => 0, 'tax_percentage' => 16, 'active' => true]);
        $this->actingAs($admin);
        $product->update(['sale_price' => 25]);
        $entry = AuditLog::where('subject_type', 'Product')->where('event', 'updated')->firstOrFail();
        $this->assertEquals(['sale_price' => '20.00'], $entry->old_values);
        $this->assertEquals(['sale_price' => 25], $entry->new_values);
        $this->assertEquals($admin->id, $entry->user_id);
        $this->patch('/users/'.$target->id, ['role_id' => $admin->role_id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'User', 'subject_id' => $target->id, 'event' => 'updated']);
        $target->refresh()->update(['password' => 'a-new-private-password']);
        $entry = AuditLog::where('subject_type', 'User')->latest('id')->firstOrFail();
        $this->assertTrue($entry->new_values['password_changed']);
        $this->assertArrayNotHasKey('password', $entry->new_values);
        $this->assertArrayNotHasKey('password', $entry->old_values ?? []);
    }

    public function test_audit_entries_roll_back_with_the_operation(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $count = AuditLog::count();
        DB::beginTransaction();
        Unit::create(['name' => 'Temporal', 'abbreviation' => 'tmp', 'allows_decimals' => false, 'active' => true]);
        $this->assertEquals($count + 1, AuditLog::count());
        DB::rollBack();
        $this->assertEquals($count, AuditLog::count());
    }

    public function test_only_admin_can_read_and_filter_audit_and_html_is_escaped(): void
    {
        $admin = User::factory()->administrator()->create();
        $seller = User::factory()->create();
        $this->get('/audit')->assertRedirect('/login');
        $this->actingAs($seller)->get('/audit')->assertForbidden();
        $this->actingAs($admin);
        AuditLog::create(['event' => 'login', 'actor_name' => '<script>alert(1)</script>', 'user_id' => $admin->id, 'created_at' => now(), 'ip_address' => '192.0.2.20']);
        AuditLog::create(['event' => 'logout', 'actor_name' => 'Otro evento', 'created_at' => now()]);
        $this->get('/audit?event=login&user_id='.$admin->id.'&search=192.0.2.20')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('Otro evento');
        $this->get('/audit?from=2026-10-20&to=2026-10-01')->assertSessionHasErrors('to');
        $this->delete('/audit/1')->assertNotFound();
    }
}
