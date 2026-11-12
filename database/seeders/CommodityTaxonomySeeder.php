<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CommodityTaxonomySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $commodities = [
            'Maize',
            'Cassava',
            'Yam',
            'Plantain',
            'Millet',
            'Sorghum',
            'Rice',
            'Groundnut',
            'Cowpea',
            'Sesame',
            'Shea Nuts',
            'Cocoa Beans',
            'Coffee Beans',
            'Cashew Nuts',
            'Tomatoes',
            'Onions',
            'Peppers',
            'Okra',
            'Palm Oil',
            'Shea Butter',
            'Cattle',
            'Poultry',
            'Goats',
            'Sheep',
            'Cotton',
        ];

        foreach ($commodities as $name) {
            Category::updateOrInsert(
                ['name' => $name, 'module_id' => 20, 'position' => 0],
                [
                    'image' => 'def.png',
                    'status' => 1,
                    'featured' => 0,
                    'products_count' => 0,
                    'childes_count' => 0,
                ]
            );
        }
    }
}
