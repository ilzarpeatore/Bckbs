<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Exercise;

class WorkoutDetailResource extends JsonResource
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
            'id'            => $this->id,
            'title'         => $this->title,
            'status'        => $this->status,
            'is_premium'    => $this->is_premium,
            'level_id'      => $this->level_id,
            'level_title'   => optional($this->level)->title,
            'level_rate'    => optional($this->level)->rate,
            'workout_image' => getSingleMedia($this, 'workout_image',null),
            'workout_type_id'       => $this->workout_type_id,
            'workout_type_title'    => optional($this->workouttype)->title,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'is_favourite'  => $this->userFavouriteWorkout->where('user_id',$user_id)->first() ? 1 : 0,
            'description' => $this->description,
        ];
    }
}
