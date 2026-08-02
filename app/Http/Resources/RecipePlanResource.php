<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipePlanResource extends JsonResource
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
            'id'            => $this->id,
            'title'         => $this->title,
            'recipe_image'  => getSingleMedia($this, 'recipe_image', null),
            'calories'      => round($this->calories),
            'protein'       => round($this->protein),
            'fats'          => round($this->fats),
            'carbs'         => round($this->carbs),
        ];
    }
}