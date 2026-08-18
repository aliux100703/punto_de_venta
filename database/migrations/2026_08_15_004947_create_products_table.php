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
        Schema::create('products', function (Blueprint $table) {
    $table->id();

    $table->foreignId('category_id')
        ->nullable()
        ->constrained('categories')
        ->nullOnDelete();

    $table->foreignId('brand_id')
        ->nullable()
        ->constrained('brands')
        ->nullOnDelete();

    $table->foreignId('unit_id')
        ->constrained('units');

    $table->string('sku', 100)->nullable()->unique();

    $table->string('barcode', 100)
        ->nullable()
        ->unique();

    $table->string('name', 200);

    $table->text('description')->nullable();

    $table->decimal('cost', 12, 2)->default(0);

    $table->decimal('sale_price', 12, 2)->default(0);

    $table->decimal('tax_percentage', 5, 2)
        ->default(16);

    $table->decimal('current_stock', 12, 3)
        ->default(0);

    $table->decimal('minimum_stock', 12, 3)
        ->default(0);

    $table->boolean('active')->default(true);

    $table->timestamps();

    $table->index('name');
    $table->index('barcode');
    $table->index('sku');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
