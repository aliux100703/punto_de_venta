<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_existing_account_becomes_administrator_without_changing_account_data(): void
    {
        $user = User::factory()->create();
        $password = $user->password;
        $migration = require database_path('migrations/2026_10_16_000001_add_role_id_to_users_table.php');
        $migration->down();
        $migration->up();
        $restored = $user->fresh();
        $this->assertTrue($restored->isAdministrator());
        $this->assertSame($user->email, $restored->email);
        $this->assertSame($password, $restored->password);
    }

    public function test_multiple_legacy_accounts_require_explicit_administrator_assignment(): void
    {
        User::factory()->count(2)->create();
        $migration = require database_path('migrations/2026_10_16_000001_add_role_id_to_users_table.php');
        $migration->down();
        $migration->up();
        foreach (User::all() as $user) {
            $this->assertTrue($user->hasRole(Role::SELLER));
        }
        $this->assertDatabaseCount('users', 2);
        $user = User::firstOrFail();
        $this->artisan('users:make-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdministrator());
        $this->artisan('users:make-admin', ['email' => 'no-existe@example.test'])->assertFailed();
    }
}
