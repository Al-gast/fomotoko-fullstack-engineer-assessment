<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Produk utama untuk simulasi flash sale dan race condition.
        Product::updateOrCreate(
            ['name' => 'Flash Sale Product'],
            [
                'price' => 100000,
                'sale_price' => 25000,
                'stock' => 10,
            ]
        );

        // Produk biasa untuk cek endpoint secara normal.
        Product::updateOrCreate(
            ['name' => 'Regular Product'],
            [
                'price' => 75000,
                'sale_price' => null,
                'stock' => 50,
            ]
        );
    }
}