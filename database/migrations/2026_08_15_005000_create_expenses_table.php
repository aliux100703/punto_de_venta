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
       Schema::create('expenses', function (Blueprint $table) {
    $table->id();

    $table->foreignId('expense_category_id')
        ->constrained('expense_categories');

    $table->foreignId('user_id')
        ->constrained('users');

    $table->foreignId('payment_method_id')
        ->nullable()
        ->constrained('payment_methods')
        ->nullOnDelete();

    $table->string('concept', 200);

    $table->decimal('amount', 12, 2);

    $table->dateTime('expense_date');

    // Ruta de foto/PDF del comprobante
    $table->string('receipt')->nullable();

    $table->text('notes')->nullable();

    $table->timestamps();

    $table->index('expense_date');
    $table->index(['expense_category_id', 'expense_date']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
