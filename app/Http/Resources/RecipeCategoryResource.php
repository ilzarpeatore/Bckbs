<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                      => $this->id,
            'title'                   => $this->title,
            'slug'                    => $this->slug,
            'status'                  => $this->status,
            'recipe_category_image'   => getSingleMedia($this, 'recipe_category_image',null),
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
        ];
    }
}