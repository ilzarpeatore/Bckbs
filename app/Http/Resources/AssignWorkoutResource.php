<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssignWorkoutResource extends JsonResource
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
            'id'            => $this->workout_id,
            'title'         => optional($this->workout)->title,
            'status'        => optional($this->workout)->status,
            'is_premium'    => optional($this->workout)->is_premium,
            'workout_image' => getSingleMedia(optional($this->workout), 'workout_image',null),
            'level_id'      => optional($this->workout)->level_id,
            'level_title'   => optional(optional($this->workout)->level)->title,
            'level_rate'    => optional(optional($this->workout)->level)->rate,
            'workout_type_id'       => optional($this->workout)->workout_type_id,
            'workout_type_title'    => optional(optional($this->workout)->workouttype)->title,
            'created_at'    => optional($this->workout)->created_at,
            'updated_at'    => optional($this->workout)->updated_at,
            'is_favourite'  => (int) $this->workout?->userFavouriteWorkout()->where('user_id', $user_id)->exists(),
        ];
    }
}
