<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('units')->insert([
            [
                'name' => 'Pieza',
                'abbreviation' => 'pza',
                'allows_decimals' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Metro',
                'abbreviation' => 'm',
                'allows_decimals' => true,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kilogramo',
                'abbreviation' => 'kg',
                'allows_decimals' => true,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Litro',
                'abbreviation' => 'l',
                'allows_decimals' => true,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Caja',
                'abbreviation' => 'caja',
                'allows_decimals' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bolsa',
                'abbreviation' => 'bolsa',
                'allows_decimals' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Rollo',
                'abbreviation' => 'rollo',
                'allows_decimals' => false,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}