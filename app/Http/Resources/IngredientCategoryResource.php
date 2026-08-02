<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IngredientCategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'     => $this->id,
            'title'  => $this->title,
            'slug'   => $this->slug,
            'status' => $this->status,
        ];
    }
}
