<?php

namespace App\Http\Resources;

use App\Services\PackageAccessService;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray($request)
    {
        $user = auth('sanctum')->user();
        $user_id = $user->id ?? null;
        $isAccessible = !$this->is_premium || ($user && PackageAccessService::canAccessPremiumRecipes($user));

        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'slug'             => $this->slug,
            'type'             => $this->type,
            'meal_type'        => $this->meal_type,
            'recipe_category'  => $this->categories->pluck('title'),
            'recipe_tag'       => $this->tags->pluck('title'),
            'preparation_time' => $this->preparation_time,
            'calories'         => round($this->calories),
            'protein'          => round($this->protein),
            'carbs'            => round($this->carbs),
            'fats'             => round($this->fats),
            'recipe_image'     => getSingleMedia($this, 'recipe_image', null),
            'is_favourite'     => $this->userFavouriteRecipe->where('user_id',$user_id)->first() ? 1 : 0,
            'is_premium'       => (bool) $this->is_premium,
            'is_accessible'    => $isAccessible,
        ];
    }
}