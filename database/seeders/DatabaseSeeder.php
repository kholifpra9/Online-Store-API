<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Product::create([
            'name'            => 'Wireless Headphones',
            'description'     => 'High quality wireless headphones with noise cancellation.',
            'price'           => 5000000,
            'inventory_count' => 100,
        ]);
 
        // Flash sale product, only 10 units available
        Product::create([
            'name'              => 'Gaming Mouse',
            'description'       => 'High-precision gaming mouse, 16000 DPI.',
            'price'             => 8000000,
            'inventory_count'   => 10,      
            'is_flash_sale'     => true,
            'flash_sale_price'  => 2000000, 
            'flash_sale_starts_at' => now()->subMinutes(5),
            'flash_sale_ends_at'   => now()->addHours(2),
        ]);
    }
}
