<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DietResource extends JsonResource
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
            'id'               => $this->id,
            'title'            => $this->title,
            'calories'         => $this->calories,
            'carbs'            => $this->carbs,
            'protein'          => $this->protein,
            'fat'              => $this->fat,
            'servings'         => $this->servings,
            'total_time'       => $this->total_time,
            'is_featured'      => $this->is_featured,
            'status'           => $this->status,
            'ingredients'      => $this->ingredients,
            'description'      => $this->description,
            'diet_image'       => getSingleMedia($this, 'diet_image',null),
            'is_premium'       => $this->is_premium,
            'categorydiet_id'  => $this->categorydiet_id,
            'categorydiet_title'  => optional($this->categorydiet)->title,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
            'is_favourite'     => $this->userFavouriteDiet->where('user_id',$user_id)->first() ? 1 : 0,
        ];
    }
}
