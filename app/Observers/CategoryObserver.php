<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        Cache::forget('all_category_names_by_id_v1');
    }

    public function deleted(Category $category): void
    {
        Cache::forget('all_category_names_by_id_v1');
    }

    public function restored(Category $category): void
    {
        Cache::forget('all_category_names_by_id_v1');
    }
}
