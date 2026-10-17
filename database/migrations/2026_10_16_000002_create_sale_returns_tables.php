<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('refunded_amount', 12, 2)->default(0);
        });
        Schema::table('sale_details', function (Blueprint $table) {
            $table->decimal('returned_quantity', 12, 3)->default(0);
            $table->decimal('refunded_total', 12, 2)->default(0);
            $table->decimal('refunded_tax', 12, 2)->default(0);
            $table->decimal('refunded_discount', 12, 2)->default(0);
            $table->boolean('allows_decimals')->nullable();
        });
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->boolean('is_cash')->nullable();
            $table->string('method_name', 100)->nullable();
        });
        DB::table('sale_payments')->orderBy('id')->chunkById(200, function ($payments) {
            foreach ($payments as $payment) {
                $name = DB::table('payment_methods')->where('id', $payment->payment_method_id)->value('name');
                $cash = DB::table('cash_movements')->where('reference_type', 'SALE')->where('reference_id', $payment->sale_id)->where('type', 'ENTRADA')->exists();
                DB::table('sale_payments')->where('id', $payment->id)->update(['is_cash' => $cash || mb_strtolower(trim($name ?? '')) === 'efectivo', 'method_name' => $name]);
            }
        });
        DB::table('sale_details')->orderBy('id')->chunkById(200, function ($details) {
            foreach ($details as $detail) {
                $allows = DB::table('products')->join('units', 'products.unit_id', '=', 'units.id')->where('products.id', $detail->product_id)->value('units.allows_decimals');
                DB::table('sale_details')->where('id', $detail->id)->update(['allows_decimals' => $allows]);
            }
        });
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 50)->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('payment_method_name', 100);
            $table->boolean('is_cash');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('taxes', 12, 2);
            $table->decimal('total', 12, 2);
            $table->text('reason');
            $table->string('external_reference', 150)->nullable();
            $table->uuid('request_key')->unique();
            $table->string('payload_hash', 64);
            $table->timestamps();
            $table->index(['sale_id', 'created_at']);
        });
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_detail_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 200);
            $table->decimal('quantity', 12, 3);
            $table->boolean('restock')->default(true);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
            $table->unique(['sale_return_id', 'sale_detail_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::table('sale_payments', fn (Blueprint $table) => $table->dropColumn(['is_cash', 'method_name']));
        Schema::table('sale_details', fn (Blueprint $table) => $table->dropColumn(['returned_quantity', 'refunded_total', 'refunded_tax', 'refunded_discount', 'allows_decimals']));
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('refunded_amount'));
    }
};
