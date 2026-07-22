<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Recipe;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
            'category' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 25);
        $category = $request->input('category');
        $search = $request->input('search');

        $query = Recipe::query()->where('status', 1);

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $totalSize = $query->count();
        $recipes = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'recipes' => $recipes,
            'total_size' => $totalSize,
        ], 200);
    }

    public function show($id)
    {
        $recipe = Recipe::where('id', $id)
            ->where('status', 1)
            ->with('ingredients')
            ->firstOrFail();

        $ingredientNames = $recipe->ingredients->pluck('name')->filter()->map(function ($name) {
            return trim($name);
        })->toArray();

        $recommendedProducts = [];
        if (!empty($ingredientNames)) {
            $matchedItems = \App\Models\Item::active()
                ->whereHas('store', function ($q) {
                    $q->where('status', 1);
                })
                ->where(function ($q) use ($ingredientNames) {
                    foreach ($ingredientNames as $name) {
                        $q->orWhere('name', 'LIKE', "%{$name}%");
                    }
                })
                ->limit(12)
                ->get();

            $recommendedProducts = Helpers::product_data_formatting($matchedItems, true, false, app()->getLocale());
        }

        return response()->json([
            'id' => $recipe->id,
            'title' => $recipe->title,
            'description' => $recipe->description,
            'image' => $recipe->image,
            'image_full_url' => $recipe->image_full_url,
            'category' => $recipe->category,
            'prep_time' => $recipe->prep_time,
            'cook_time' => $recipe->cook_time,
            'servings' => $recipe->servings,
            'difficulty' => $recipe->difficulty,
            'status' => $recipe->status,
            'ingredients' => $recipe->ingredients,
            'recommended_products' => $recommendedProducts,
            'matched_products' => $recommendedProducts,
        ], 200);
    }

    public function ingredients($id)
    {
        $recipe = Recipe::where('id', $id)
            ->where('status', 1)
            ->firstOrFail();

        $ingredients = $recipe->ingredients;

        return response()->json([
            'recipe_id' => $recipe->id,
            'ingredients' => $ingredients,
        ], 200);
    }
}
