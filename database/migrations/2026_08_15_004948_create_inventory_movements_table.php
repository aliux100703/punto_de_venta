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
        Schema::create('inventory_movements', function (Blueprint $table) {
    $table->id();

    $table->foreignId('product_id')
        ->constrained('products');

    $table->foreignId('user_id')
        ->constrained('users');

    $table->enum('type', [
        'ENTRADA',
        'VENTA',
        'DEVOLUCION',
        'AJUSTE_POSITIVO',
        'AJUSTE_NEGATIVO',
        'MERMA'
    ]);

    $table->decimal('quantity', 12, 3);

    $table->decimal('previous_stock', 12, 3);

    $table->decimal('new_stock', 12, 3);

    $table->string('reference_type', 50)
        ->nullable();

    $table->unsignedBigInteger('reference_id')
        ->nullable();

    $table->string('reason')
        ->nullable();

    $table->timestamps();

    $table->index(['product_id', 'created_at']);
    $table->index(['reference_type', 'reference_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
