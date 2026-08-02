<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeDetailResource;
use App\Http\Resources\RecipeIngredientResource;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\RecipeStepResource;
use App\Models\Recipe;
use App\Models\UserFavouriteRecipe;
use App\Services\PackageAccessService;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function getRecipeFilterList(Request $request)
    {
        $recipe = Recipe::recipeFilter();

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->filled('per_page')) {
            if ($request->per_page == -1) {
                $per_page = $recipe->count();
            }elseif (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
        }

        $recipe = $recipe->paginate($per_page);

        $items = RecipeResource::collection($recipe);
        
        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ]);
    }

    public function recipeDetail($id)
    {
        $recipe = Recipe::active()->find($id);        
      
        if (!$recipe) {
            return json_custom_response( __('message.not_found_entry', ['name' => __('message.recipe')]), 404);            
        }

        $user = auth('sanctum')->user();
        $isAccessible = !$recipe->is_premium || ($user && PackageAccessService::canAccessPremiumRecipes($user));

        $recipe_data = new RecipeDetailResource($recipe);
        $response = [
            'data'               => $recipe_data,
            'recipe_steps'       => $isAccessible ? RecipeStepResource::collection($recipe->steps) : [],
            'recipe_ingredients' => $isAccessible ? RecipeIngredientResource::collection($recipe->recipeIngredients) : [],
        ];
        return json_custom_response($response);
    }

    public function saveUserFavouriteRecipe(Request $request)
    {
        $user_id = auth()->id();
        $recipe_id = $request->recipe_id;

        $recipe = Recipe::where('id', $recipe_id )->first();
        if( $recipe == null )
        {
            return json_message_response( __('message.not_found_entry',['name' => __('message.recipe') ]) );
        }
        $user_favourite_recipe = UserFavouriteRecipe::myFavouriteRecipe()->where('recipe_id',$recipe_id)->first();
        
        if($user_favourite_recipe != null) {
            $user_favourite_recipe->delete();
            $message = __('message.unfavourite_recipe_list');
        } else {
            $data = [
                'user_id'   => $user_id,
                'recipe_id'   => $recipe_id,
            ];
            
            UserFavouriteRecipe::create($data);
            $message = __('message.favourite_recipe_list');
        }

        return json_message_response($message);
    }

    public function getUserFavouriteRecipe(Request $request)
    {
        $recipe = Recipe::myFavouriteRecipe();

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)) {
            if(is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ) {
                $per_page = $recipe->count();
            }
        }

        $recipe = $recipe->orderByMyFavouriteDesc()->paginate($per_page);

        $items = RecipeResource::collection($recipe);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }
}
