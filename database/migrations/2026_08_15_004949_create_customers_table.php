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
       Schema::create('customers', function (Blueprint $table) {
    $table->id();

    $table->string('name', 150);
    $table->string('business_name', 200)->nullable();
    $table->string('rfc', 20)->nullable();

    $table->string('phone', 30)->nullable();
    $table->string('email', 150)->nullable();

    $table->string('address')->nullable();
    $table->string('postal_code', 10)->nullable();

    // Datos para futura facturación
    $table->string('tax_regime', 100)->nullable();
    $table->string('cfdi_use', 20)->nullable();

    $table->boolean('active')->default(true);

    $table->timestamps();

    $table->index('name');
    $table->index('rfc');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
