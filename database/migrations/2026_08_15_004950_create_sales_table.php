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
       Schema::create('sales', function (Blueprint $table) {
    $table->id();

    $table->string('folio', 50)->unique();

    $table->foreignId('customer_id')
        ->nullable()
        ->constrained('customers')
        ->nullOnDelete();

    $table->foreignId('seller_id')
        ->constrained('users');

    $table->decimal('subtotal', 12, 2)->default(0);
    $table->decimal('discount', 12, 2)->default(0);
    $table->decimal('taxes', 12, 2)->default(0);
    $table->decimal('total', 12, 2)->default(0);

    $table->enum('status', [
        'COMPLETADA',
        'CANCELADA',
        'DEVOLUCION_PARCIAL',
        'DEVUELTA'
    ])->default('COMPLETADA');

    $table->text('notes')->nullable();

    $table->timestamps();

    $table->index('created_at');
    $table->index(['seller_id', 'created_at']);
    $table->index('customer_id');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
