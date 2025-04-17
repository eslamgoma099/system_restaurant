<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ingredient;

class IngredientSeeder extends Seeder
{
    public function run()
    {
        Ingredient::create([
            'name' => 'جبنة',
            'unit' => 'كجم',
            'cost_per_unit' => 10.00,
            'branch_id' => 1,
        ]);

        Ingredient::create([
            'name' => 'صوص',
            'unit' => 'لتر',
            'cost_per_unit' => 5.00,
            'branch_id' => 1,
        ]);
    }
}