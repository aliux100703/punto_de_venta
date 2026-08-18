<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('cash_registers', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')
        ->constrained('users');

    $table->dateTime('opened_at');

    $table->dateTime('closed_at')->nullable();

    $table->decimal('opening_amount', 12, 2)->default(0);

    $table->decimal('expected_cash', 12, 2)->nullable();

    $table->decimal('counted_cash', 12, 2)->nullable();

    $table->decimal('difference', 12, 2)->nullable();

    $table->enum('status', [
        'ABIERTA',
        'CERRADA'
    ])->default('ABIERTA');

    $table->text('notes')->nullable();

    $table->timestamps();

    $table->index(['user_id', 'status']);
    $table->index('opened_at');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
