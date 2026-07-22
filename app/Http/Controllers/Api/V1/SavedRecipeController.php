<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SavedRecipe;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\Validator;

class SavedRecipeController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $saved = SavedRecipe::where('user_id', $user->id)
            ->with('recipe')
            ->latest()
            ->get();

        $recipes = $saved->map(function ($item) {
            return $item->recipe;
        });

        return response()->json([
            'saved_recipes' => $recipes,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipe_id' => 'required|integer|exists:recipes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user = Auth::user();

        $existing = SavedRecipe::where('user_id', $user->id)
            ->where('recipe_id', $request->recipe_id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => translate('messages.already_saved'),
            ], 200);
        }

        SavedRecipe::create([
            'user_id' => $user->id,
            'recipe_id' => $request->recipe_id,
        ]);

        return response()->json([
            'message' => translate('messages.recipe_saved_successfully'),
        ], 200);
    }

    public function destroy($id)
    {
        $user = Auth::user();

        $saved = SavedRecipe::where('user_id', $user->id)
            ->where('recipe_id', $id)
            ->firstOrFail();

        $saved->delete();

        return response()->json([
            'message' => translate('messages.recipe_removed_successfully'),
        ], 200);
    }
}
