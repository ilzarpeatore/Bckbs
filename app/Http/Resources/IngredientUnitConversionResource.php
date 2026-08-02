<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IngredientUnitConversionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'ingredient_id'       => $this->ingredient_id,
            'measurement_unit_id' => $this->measurement_unit_id,
            'gram_equivalent'     => $this->gram_equivalent,
        ];
    }
}
