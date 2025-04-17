<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory;

class InventorySeeder extends Seeder
{
    public function run()
    {
        Inventory::create([
            'item_id' => 1,
            'ingredient_id' => 3,
            'branch_id' => 1,
            'quantity' => 100,
        ]);

        Inventory::create([
            'item_id' => 2,
            'ingredient_id' => 2,
            'branch_id' => 1,
            'quantity' => 50,
        ]);
    }
}
