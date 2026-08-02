<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutDayResource extends JsonResource
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
            'workout_image' => getSingleMedia($this->workout, 'workout_image',null),
            'workout_id'    => $this->workout_id,
            'workout_title' => optional($this->workout)->title,
            'is_rest'       => $this->is_rest,
            'sequence'      => $this->sequence,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
