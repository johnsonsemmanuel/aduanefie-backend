<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if Farm Inputs module already exists
        $existing = DB::table('modules')->where('module_type', 'farm_inputs')->first();
        if ($existing) {
            return;
        }

        $now = now()->toDateTimeString();

        // 1. Create the module row
        DB::table('modules')->insert([
            'module_name'       => 'Farm Inputs',
            'module_type'       => 'farm_inputs',
            'thumbnail'         => 'def.png',
            'status'            => 1,
            'stores_count'      => 0,
            'icon'              => 'def.png',
            'theme_id'          => 1,
            'description'       => 'Seeds, fertilizers, farm tools, small equipment, and animal feed.',
            'short_description' => 'Everything your farm needs',
            'all_zone_service'  => 0,
            'slug'              => 'farm-inputs',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        $moduleId = DB::getPdo()->lastInsertId();

        // 2. Module translations
        DB::table('translations')->insert([
            ['translationable_type' => 'App\Models\Module', 'translationable_id' => $moduleId, 'locale' => 'en', 'key' => 'module_name', 'value' => 'Farm Inputs', 'created_at' => $now, 'updated_at' => $now],
            ['translationable_type' => 'App\Models\Module', 'translationable_id' => $moduleId, 'locale' => 'en', 'key' => 'description', 'value' => 'Seeds, fertilizers, farm tools, small equipment, and animal feed.', 'created_at' => $now, 'updated_at' => $now],
            ['translationable_type' => 'App\Models\Module', 'translationable_id' => $moduleId, 'locale' => 'en', 'key' => 'short_description', 'value' => 'Everything your farm needs', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 3. Create top-level categories (parent_id = 0)
        $categories = [
            ['name' => 'Seeds',           'slug' => 'seeds'],
            ['name' => 'Fertilizers',     'slug' => 'fertilizers'],
            ['name' => 'Farm Tools',      'slug' => 'farm-tools'],
            ['name' => 'Small Equipment', 'slug' => 'small-equipment'],
            ['name' => 'Animal Feed',     'slug' => 'animal-feed'],
        ];

        foreach ($categories as $i => $cat) {
            DB::table('categories')->insert([
                'name'       => $cat['name'],
                'image'      => 'def.png',
                'parent_id'  => 0,
                'position'   => $i,
                'status'     => 1,
                'priority'   => $i,
                'module_id'  => $moduleId,
                'slug'       => $cat['slug'],
                'featured'   => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $catId = DB::getPdo()->lastInsertId();

            DB::table('translations')->insert([
                ['translationable_type' => 'App\Models\Category', 'translationable_id' => $catId, 'locale' => 'en', 'key' => 'name', 'value' => $cat['name'], 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        // 4. Link module to all active zones
        $zones = DB::table('zones')->where('status', 1)->pluck('id');

        foreach ($zones as $zoneId) {
            DB::table('module_zone')->insert([
                'module_id'                       => $moduleId,
                'zone_id'                         => $zoneId,
                'per_km_shipping_charge'          => 10.00,
                'minimum_shipping_charge'         => 10.00,
                'maximum_cod_order_amount'        => 50000.00,
                'maximum_shipping_charge'         => 500.00,
                'delivery_charge_type'            => 'distance',
                'fixed_shipping_charge'           => null,
                'additional_delivery_option_status' => 0,
                'minimum_delivery_time'           => 60,
                'minimum_delivery_charge'         => 10.00,
            ]);
        }
    }

    public function down(): void
    {
        $module = DB::table('modules')->where('module_type', 'farm_inputs')->first();
        if (!$module) {
            return;
        }

        DB::table('translations')->where('translationable_type', 'App\Models\Module')->where('translationable_id', $module->id)->delete();
        DB::table('translations')->whereIn('translationable_id', function ($q) use ($module) {
            $q->select('id')->from('categories')->where('module_id', $module->id);
        })->where('translationable_type', 'App\Models\Category')->delete();
        DB::table('categories')->where('module_id', $module->id)->delete();
        DB::table('module_zone')->where('module_id', $module->id)->delete();
        DB::table('modules')->where('id', $module->id)->delete();
    }
};
