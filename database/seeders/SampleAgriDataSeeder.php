<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use App\Models\Item;
use App\Models\Category;
use App\Models\BulkPricingTier;

class SampleAgriDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create sample vendor users and stores
        $vendors = [
            [
                'name' => 'Green Valley Co-op',
                'email' => 'greenvalley@coop.gh',
                'phone' => '+233242371341',
                'country' => 'Ghana',
                'zone' => 'Greater Accra',
                'commodities' => [
                    ['name' => 'Maize (White)', 'price' => 2.50, 'unit' => 'kg', 'hs_code' => '100590', 'hs_description' => 'Maize (corn), not for sowing', 'min_qty' => 500, 'certification' => 'GlobalGAP', 'cold_chain' => 0],
                    ['name' => 'Cocoa Beans', 'price' => 6.80, 'unit' => 'kg', 'hs_code' => '180100', 'hs_description' => 'Cocoa beans, whole or broken', 'min_qty' => 1000, 'certification' => 'Fairtrade', 'cold_chain' => 0],
                ],
            ],
            [
                'name' => 'Sahel Exporters Ltd',
                'email' => 'sahel@exporters.ng',
                'phone' => '+2348063086265',
                'country' => 'Nigeria',
                'zone' => 'Lagos',
                'commodities' => [
                    ['name' => 'Cassava Tubers', 'price' => 1.20, 'unit' => 'kg', 'hs_code' => '071410', 'hs_description' => 'Cassava (manioc), fresh', 'min_qty' => 2000, 'certification' => 'HACCP', 'cold_chain' => 1],
                    ['name' => 'Sesame Seeds', 'price' => 3.50, 'unit' => 'kg', 'hs_code' => '120740', 'hs_description' => 'Sesamum seeds', 'min_qty' => 500, 'certification' => 'Organic', 'cold_chain' => 0],
                ],
            ],
            [
                'name' => 'Highland Farmers Coop',
                'email' => 'highland@coop.ke',
                'phone' => '+254703266946',
                'country' => 'Kenya',
                'zone' => 'Nairobi',
                'commodities' => [
                    ['name' => 'Tea Leaves', 'price' => 4.20, 'unit' => 'kg', 'hs_code' => '090210', 'hs_description' => 'Green tea (not fermented)', 'min_qty' => 2000, 'certification' => 'Rainforest Alliance', 'cold_chain' => 0],
                    ['name' => 'Cut Flowers', 'price' => 8.50, 'unit' => 'kg', 'hs_code' => '060310', 'hs_description' => 'Fresh cut flowers and buds', 'min_qty' => 100, 'certification' => 'Fairtrade', 'cold_chain' => 1],
                ],
            ],
            [
                'name' => 'Côte d\'Ivoire Agri-Export',
                'email' => 'export@agri.ci',
                'phone' => '+22501234567',
                'country' => "Côte d'Ivoire",
                'zone' => 'Abidjan',
                'commodities' => [
                    ['name' => 'Cashew Nuts', 'price' => 7.20, 'unit' => 'kg', 'hs_code' => '080131', 'hs_description' => 'Cashew nuts, in shell', 'min_qty' => 1000, 'certification' => 'Fairtrade', 'cold_chain' => 0],
                    ['name' => 'Shea Butter', 'price' => 9.50, 'unit' => 'kg', 'hs_code' => '151590', 'hs_description' => 'Shea butter (karite butter)', 'min_qty' => 500, 'certification' => 'Organic', 'cold_chain' => 0],
                ],
            ],
        ];

        $categoryIds = Category::where('module_id', 20)->pluck('id')->toArray();

        foreach ($vendors as $vendorData) {
            // Create user
            $nameParts = explode(' ', $vendorData['name'], 2);
            $user = User::create([
                'f_name' => $nameParts[0] ?? $vendorData['name'],
                'l_name' => $nameParts[1] ?? '',
                'email' => $vendorData['email'],
                'password' => bcrypt('password'),
                'phone' => $vendorData['phone'],
                'status' => 1,
            ]);

            // Create zone or use existing Ghana zone
            $zoneId = null;
            if ($vendorData['country'] === 'Ghana') {
                $zoneId = 2; // Greater Accra zone exists
            }

            // Create store
            $store = Store::create([
                'name' => $vendorData['name'],
                'email' => $vendorData['email'],
                'phone' => $vendorData['phone'],
                'address' => $vendorData['zone'] . ', ' . $vendorData['country'],
                'status' => 1,
                'vendor_id' => $user->id,
                'zone_id' => $zoneId,
                'verification_tier' => 'exporter',
                'verification_status' => 'verified',
                'featured' => 1,
                'module_id' => 20,
            ]);

            // Create commodities
            $catIndex = 0;
            foreach ($vendorData['commodities'] as $comm) {
                $catId = $categoryIds[$catIndex % count($categoryIds)] ?? null;
                $catIndex++;

                $item = Item::create([
                    'name' => $comm['name'],
                    'slug' => strtolower(str_replace(' ', '-', $comm['name'])),
                    'description' => 'Premium ' . strtolower($comm['name']) . ' sourced from ' . $vendorData['country'] . '. Available for cross-border trade.',
                    'short_description' => $comm['name'] . ' from ' . $vendorData['country'],
                    'price' => $comm['price'],
                    'discount' => 0,
                    'discount_type' => null,
                    'stock' => 10000,
                    'unit' => $comm['unit'],
                    'module_id' => 20,
                    'store_id' => $store->id,
                    'category_id' => $catId,
                    'status' => 1,
                    'featured' => 1,
                    'cold_chain_required' => $comm['cold_chain'],
                    'origin_farm' => $vendorData['country'],
                    'certification' => $comm['certification'],
                    'minimum_order_qty' => $comm['min_qty'],
                    'hs_code' => $comm['hs_code'],
                    'hs_description' => $comm['hs_description'],
                    'seasonality' => 'Year-round',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Create bulk pricing tiers
                if ($item->id) {
                    BulkPricingTier::create([
                        'item_id' => $item->id,
                        'min_quantity' => $comm['min_qty'],
                        'max_quantity' => $comm['min_qty'] * 10,
                        'discount_percent' => 5.0,
                        'currency_code' => 'USD',
                        'status' => 'active',
                    ]);
                    BulkPricingTier::create([
                        'item_id' => $item->id,
                        'min_quantity' => $comm['min_qty'] * 10,
                        'max_quantity' => null,
                        'discount_percent' => 10.0,
                        'currency_code' => 'USD',
                        'status' => 'active',
                    ]);
                }
            }
        }
    }
}
