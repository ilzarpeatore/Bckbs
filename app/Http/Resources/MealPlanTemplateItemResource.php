<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanTemplateItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'day_key'   => $this->day_key,
            'meal_type' => $this->meal_type,
            'recipe_id' => $this->recipe_id,
            'calories'  => round($this->calories),
            'protein'   => round($this->protein),
            'fats'      => round($this->fats),
            'carbs'     => round($this->carbs),
            'recipe'    => $this->whenLoaded('recipe', fn () => new RecipePlanResource($this->recipe)),
        ];
    }
}
