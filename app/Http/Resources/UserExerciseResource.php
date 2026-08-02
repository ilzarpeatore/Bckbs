<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\BodyPart;

class UserExerciseResource extends JsonResource
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
            'id'            => $this->exercise->id,
            'title'         => $this->exercise->title,
            'status'        => $this->exercise->status,
            'is_premium'    => $this->exercise->is_premium,
            'exercise_image'=> getSingleMedia($this->exercise, 'exercise_image', null),
            'duration'      => $this->exercise->duration,
            'sets'          => $this->exercise->sets,
            'based'         => $this->exercise->based,
            'type'          => $this->exercise->type,
            'seconds_per_rep' => $this->exercise?->seconds_per_rep ?? 4,
            'created_at'    => $this->exercise->created_at,
            'updated_at'    => $this->exercise->updated_at,
        ];
    }
}