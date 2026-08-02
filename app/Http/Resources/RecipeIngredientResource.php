<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeIngredientResource extends JsonResource
{
    public function toArray($request)
    {

        return [
            'id'                     => $this->id,
            'ingredient_id'          => $this->ingredient_id,
            'ingredient_title'       => optional($this->ingredient)->title,
            'measurement_unit_id'    => $this->measurement_unit_id,
            'measurement_unit_title' => optional($this->measurementUnit)->title,
            'quantity'               => $this->quantity,
            'amount'                 => $this->amount,
            'quantity_grams'         => $this->quantity_grams,
            'quantity_display'       => $this->display_quantity,
            'calories'               => round($this->calories),
            'protein'                => round($this->protein),
            'fats'                   => round($this->fats),
            'carbs'                  => round($this->carbs),
        ];
    }
}