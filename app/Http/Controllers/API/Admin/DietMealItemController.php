<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Diet;
use App\Models\DietMealItem;
use App\Models\Recipe;
use App\Http\Resources\DietMealItemResource;

class DietMealItemController extends Controller
{
    public function index($dietId)
    {
        $diet = Diet::find($dietId);

        if (!$diet) {
            return json_message_response('Diet not found.', 404);
        }

        $items = DietMealItem::where('diet_id', $dietId)->with('recipe')->get();

        return json_custom_response([
            'data' => [
                'diet'  => ['id' => $diet->id, 'title' => $diet->title],
                'items' => DietMealItemResource::collection($items),
            ],
        ]);
    }

    public function store(Request $request, $dietId)
    {
        $diet = Diet::find($dietId);

        if (!$diet) {
            return json_message_response('Diet not found.', 404);
        }

        $request->validate([
            'meal_type' => 'required|in:' . implode(',', config('macro-nutrient.MEAL_TYPE')),
            'recipe_id' => 'required|exists:recipes,id',
        ]);

        $recipe = Recipe::find($request->recipe_id);

        $item = DietMealItem::create([
            'diet_id'   => $diet->id,
            'meal_type' => $request->meal_type,
            'recipe_id' => $recipe->id,
            'calories'  => $recipe->calories,
            'protein'   => $recipe->protein,
            'fats'      => $recipe->fats,
            'carbs'     => $recipe->carbs,
        ]);

        return json_custom_response([
            'message' => 'Recipe added to diet.',
            'data'    => new DietMealItemResource($item->load('recipe')),
        ], 201);
    }

    public function destroy($itemId)
    {
        $item = DietMealItem::find($itemId);

        if (!$item) {
            return json_message_response('Item not found.', 404);
        }

        $item->delete();

        return json_message_response('Item removed.');
    }
}
