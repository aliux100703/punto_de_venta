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
       Schema::create('quotes', function (Blueprint $table) {
    $table->id();

    $table->string('folio', 50)->unique();

    $table->foreignId('customer_id')
        ->nullable()
        ->constrained('customers')
        ->nullOnDelete();

    $table->foreignId('user_id')
        ->constrained('users');

    $table->decimal('subtotal', 12, 2)->default(0);
    $table->decimal('discount', 12, 2)->default(0);
    $table->decimal('taxes', 12, 2)->default(0);
    $table->decimal('total', 12, 2)->default(0);

    $table->enum('status', [
        'PENDIENTE',
        'ACEPTADA',
        'RECHAZADA',
        'VENCIDA',
        'CONVERTIDA'
    ])->default('PENDIENTE');

    $table->date('expiration_date')->nullable();

    // Se llena cuando la cotización se convierte en venta
    $table->foreignId('sale_id')
        ->nullable()
        ->constrained('sales')
        ->nullOnDelete();

    $table->text('notes')->nullable();

    $table->timestamps();

    $table->index('customer_id');
    $table->index('status');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
