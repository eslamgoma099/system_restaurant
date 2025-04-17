<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
// use InventorySeeder;
use Database\Seeders\InventorySeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            IngredientSeeder::class,
            InventorySeeder::class, // ← تأكد أن هذا موجود
        ]);
    }
}
