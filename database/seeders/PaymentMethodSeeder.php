<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('payment_methods')->upsert([
            [
                'name' => 'Efectivo',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tarjeta',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Transferencia',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['name'], ['name']);
    }
}
