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
       Schema::create('quote_details', function (Blueprint $table) {
    $table->id();

    $table->foreignId('quote_id')
        ->constrained('quotes')
        ->cascadeOnDelete();

    $table->foreignId('product_id')
        ->constrained('products');

    $table->decimal('quantity', 12, 3);

    $table->decimal('unit_price', 12, 2)->default(0);

    $table->decimal('discount', 12, 2)->default(0);

    $table->decimal('tax_percentage', 5, 2)->default(0);
    $table->decimal('tax', 12, 2)->default(0);

    $table->decimal('subtotal', 12, 2)->default(0);
    $table->decimal('total', 12, 2)->default(0);

    $table->timestamps();

    $table->index('quote_id');
    $table->index('product_id');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_details');
    }
};
