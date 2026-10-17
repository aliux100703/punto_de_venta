<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
        });
        foreach (['Administrador', 'Vendedor'] as $name) {
            DB::table('roles')->insertOrIgnore(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
        $seller = DB::table('roles')->where('name', 'Vendedor')->value('id');
        DB::table('users')->whereNull('role_id')->update(['role_id' => $seller]);
        // A single existing account is the installation owner. Multiple accounts
        // require an explicit administrator assignment through the console.
        if (DB::table('users')->count() === 1) {
            DB::table('users')->update(['role_id' => DB::table('roles')->where('name', 'Administrador')->value('id')]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('role_id'));
    }
};
