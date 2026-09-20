<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\NormalizesFatSecretRecipePreview;
use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanTemplateItemResource extends JsonResource
{
    use NormalizesFatSecretRecipePreview;

    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'day_key'             => $this->day_key,
            'meal_type'           => $this->meal_type,
            'recipe_id'           => $this->recipe_id,
            'fatsecret_recipe_id' => $this->fatsecret_recipe_id,
            'calories'            => round($this->calories),
            'protein'             => round($this->protein),
            'fats'                => round($this->fats),
            'carbs'               => round($this->carbs),
            // Mismo shape para ambos orígenes -- ver
            // docs/FATSECRET_INTEGRATION.md sección 9. Se comprueba
            // recipe_id ANTES de tocar la relación para no forzar un lazy
            // load en un item de FatSecret (que nunca la tiene).
            'recipe' => $this->recipe_id
                ? $this->whenLoaded('recipe', fn () => new RecipePlanResource($this->recipe))
                : $this->resolveFatSecretRecipePreview($this->fatsecret_recipe_id, $this->calories, $this->protein, $this->fats, $this->carbs),
        ];
    }
}
