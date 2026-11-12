<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sync stale default-locale (`en`) translations that still carry pre-edit values
     * (such as `-w` suffixed category labels) and rework the Agro Services module
     * (`id = 6`) from `ecommerce` to native `service` type.
     */
    public function up(): void
    {
        // 1. Sync English (`en`) translations for categories whose base `name` was updated
        //    while the hidden `en` tab kept the previous string (e.g. `Meat & Poultry-w`).
        $mismatchedCategories = DB::table('categories as c')
            ->leftJoin('translations as t', function ($join) {
                $join->on('t.translationable_id', '=', 'c.id')
                    ->where('t.translationable_type', '=', 'App\\Models\\Category')
                    ->where('t.locale', '=', 'en')
                    ->where('t.key', '=', 'name');
            })
            ->whereNotNull('c.name')
            ->where('c.name', '!=', '')
            ->where(function ($query) {
                $query->whereNull('t.id')
                    ->orWhereColumn('c.name', '!=', 't.value');
            })
            ->select('c.id', 'c.name', 't.id as translation_id')
            ->get();

        foreach ($mismatchedCategories as $cat) {
            if ($cat->translation_id) {
                DB::table('translations')
                    ->where('id', $cat->translation_id)
                    ->update(['value' => $cat->name, 'updated_at' => now()]);
            } else {
                DB::table('translations')->insert([
                    'translationable_type' => 'App\\Models\\Category',
                    'translationable_id' => $cat->id,
                    'locale' => 'en',
                    'key' => 'name',
                    'value' => $cat->name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Sync mismatched English (`en`) translations for stores and modules.
        $mismatchedStores = DB::table('stores as s')
            ->join('translations as t', function ($join) {
                $join->on('t.translationable_id', '=', 's.id')
                    ->where('t.translationable_type', '=', 'App\\Models\\Store')
                    ->where('t.locale', '=', 'en')
                    ->where('t.key', '=', 'name');
            })
            ->whereNotNull('s.name')
            ->where('s.name', '!=', '')
            ->whereColumn('s.name', '!=', 't.value')
            ->select('t.id as translation_id', 's.name')
            ->get();

        foreach ($mismatchedStores as $store) {
            DB::table('translations')
                ->where('id', $store->translation_id)
                ->update(['value' => $store->name, 'updated_at' => now()]);
        }

        $mismatchedModules = DB::table('modules as m')
            ->join('translations as t', function ($join) {
                $join->on('t.translationable_id', '=', 'm.id')
                    ->where('t.translationable_type', '=', 'App\\Models\\Module')
                    ->where('t.locale', '=', 'en')
                    ->where('t.key', '=', 'module_name');
            })
            ->whereNotNull('m.module_name')
            ->where('m.module_name', '!=', '')
            ->whereColumn('m.module_name', '!=', 't.value')
            ->select('t.id as translation_id', 'm.module_name')
            ->get();

        foreach ($mismatchedModules as $mod) {
            DB::table('translations')
                ->where('id', $mod->translation_id)
                ->update(['value' => $mod->module_name, 'updated_at' => now()]);
        }

        // 3. Rework Agro Services & Mechanization (module 6) from `ecommerce` to `service`.
        DB::table('modules')
            ->where('id', 6)
            ->update([
                'module_type' => 'service',
                'updated_at' => now(),
            ]);

        Cache::forget('all_category_names_by_id_v1');
    }

    public function down(): void
    {
        DB::table('modules')
            ->where('id', 6)
            ->update([
                'module_type' => 'ecommerce',
                'updated_at' => now(),
            ]);
    }
};
