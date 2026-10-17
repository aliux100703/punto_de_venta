<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('categories')->upsert([
            [
                'name' => 'Herramientas',
                'description' => 'Herramientas manuales y eléctricas',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tornillería',
                'description' => 'Tornillos, tuercas, rondanas y accesorios',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Plomería',
                'description' => 'Material y accesorios de plomería',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Electricidad',
                'description' => 'Material y accesorios eléctricos',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pintura',
                'description' => 'Pinturas y accesorios',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Construcción',
                'description' => 'Material para construcción',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Jardinería',
                'description' => 'Herramientas y productos para jardín',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['name'], ['name']);
    }
}
