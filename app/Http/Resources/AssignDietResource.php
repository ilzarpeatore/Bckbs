<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssignDietResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user_id = auth('sanctum')->id() ?? null;
        return [
            'id'               => $this->diet_id,
            'title'            => optional($this->diet)->title,
            'calories'         => optional($this->diet)->calories,
            'carbs'            => optional($this->diet)->carbs,
            'protein'          => optional($this->diet)->protein,
            'fat'              => optional($this->diet)->fat,
            'servings'         => optional($this->diet)->servings,
            'total_time'       => optional($this->diet)->total_time,
            'is_featured'      => optional($this->diet)->is_featured,
            'status'           => optional($this->diet)->status,
            'ingredients'      => optional($this->diet)->ingredients,
            'description'      => optional($this->diet)->description,
            'diet_image'       => getSingleMedia(optional($this->diet), 'diet_image',null),
            'is_premium'       => optional($this->diet)->is_premium,
            'categorydiet_id'  => optional($this->diet)->categorydiet_id,
            'categorydiet_title'  => optional(optional($this->diet)->categorydiet)->title,
            'created_at'       => optional($this->diet)->created_at,
            'updated_at'       => optional($this->diet)->updated_at,
            'is_favourite'     => (int) $this->diet?->userFavouriteDiet()->where('user_id', $user_id)->exists() ? 1 : 0,
        ];
    }
}
