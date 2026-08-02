<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                     => $this->id,
            'title'                  => $this->title,
            'slug'                   => $this->slug,
            'ingredient_category_id' => $this->ingredient_category_id,
            'calories_per_gram'      => $this->calories_per_gram,
            'protein_per_gram'       => $this->protein_per_gram,
            'fat_per_gram'           => $this->fat_per_gram,
            'carbs_per_gram'         => $this->carbs_per_gram,
            'density'                => $this->density,
            'status'                 => $this->status,
        ];
    }
}
