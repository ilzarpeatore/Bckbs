<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Http\Resources\RecipeIngredientResource;
use App\Traits\HandlesRecipeIngredients;

class RecipeIngredientController extends Controller
{
    use HandlesRecipeIngredients;

    public function getList(Request $request)
    {
        $request->validate(['recipe_id' => 'required|exists:recipes,id']);

        $items = RecipeIngredient::where('recipe_id', $request->recipe_id)
            ->with('ingredient', 'measurementUnit')
            ->orderBy('id')
            ->get();

        return json_custom_response([
            'data' => RecipeIngredientResource::collection($items),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'recipe_id'    => 'required|exists:recipes,id',
            'ingredients'  => 'required|array|min:1',
            'ingredients.*.ingredient_id'          => 'required|exists:ingredients,id',
            'ingredients.*.measurement_unit_id'    => 'nullable|exists:measurement_units,id',
            'ingredients.*.quantity'               => 'required|numeric|min:0.01',
            'ingredients.*.quantity_grams'         => 'nullable|numeric|min:0',
        ]);

        $recipe = Recipe::find($request->recipe_id);
        $this->saveRecipeIngredient($recipe, $request->ingredients);

        $items = RecipeIngredient::where('recipe_id', $recipe->id)
            ->with('ingredient', 'measurementUnit')
            ->get();

        return json_custom_response([
            'message' => 'Recipe ingredients updated successfully.',
            'data'    => [
                'ingredients' => RecipeIngredientResource::collection($items),
                'calories'    => $recipe->fresh()->calories,
                'protein'     => $recipe->fresh()->protein,
                'fats'        => $recipe->fresh()->fats,
                'carbs'       => $recipe->fresh()->carbs,
            ],
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'id'         => 'required|exists:recipe_ingredients,id',
            'recipe_id'  => 'required|exists:recipes,id',
        ]);

        $item = RecipeIngredient::find($request->id);
        $recipe = Recipe::find($request->recipe_id);
        $item->delete();

        // Recalculate totals
        $this->recalculateRecipeTotals($recipe);

        return json_message_response('Ingredient removed successfully.');
    }

    private function recalculateRecipeTotals(Recipe $recipe): void
    {
        $totals = RecipeIngredient::where('recipe_id', $recipe->id)
            ->selectRaw('SUM(calories) as cal, SUM(protein) as pro, SUM(fats) as fat, SUM(carbs) as carb')
            ->first();

        $recipe->update([
            'calories' => $totals->cal ?? 0,
            'protein'  => $totals->pro ?? 0,
            'fats'     => $totals->fat ?? 0,
            'carbs'    => $totals->carb ?? 0,
        ]);
    }
}
