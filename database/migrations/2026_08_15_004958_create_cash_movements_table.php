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
        Schema::create('cash_movements', function (Blueprint $table) {
    $table->id();

    $table->foreignId('cash_register_id')
        ->constrained('cash_registers')
        ->cascadeOnDelete();

    $table->foreignId('user_id')
        ->constrained('users');

    $table->enum('type', [
        'ENTRADA',
        'SALIDA'
    ]);

    $table->string('concept', 150);

    $table->decimal('amount', 12, 2);

    $table->string('reference_type', 50)->nullable();

    $table->unsignedBigInteger('reference_id')->nullable();

    $table->text('notes')->nullable();

    $table->timestamps();

    $table->index(['cash_register_id', 'created_at']);
    $table->index(['reference_type', 'reference_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
