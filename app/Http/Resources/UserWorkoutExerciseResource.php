<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserWorkoutExerciseResource extends JsonResource
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
            'id'                => $this->id,
            'exercise_id'       => $this->exercise_id,
            'workout_id'        => $this->workout_id,
            'workout_day_id'    => $this->workout_day_id,
            'exercise_title'    => optional($this->exercise)->title,
            'exercise_image'    => getSingleMedia($this->exercise, 'exercise_image', null),
            'exercise_is_premium' => optional($this->exercise)->is_premium,
            'workout_image'     => getSingleMedia(optional($this->workout), 'workout_image',null),
            'workout_title'     => optional($this->workout)->title,
            // Mismos nombres que ya espera WorkoutExerciseHistoryItem en el
            // frontend (api/workoutHistory.ts) - antes no se persistian.
            'sets'              => $this->logged_sets ?? [],
            'date'              => optional($this->date)->format('Y-m-d'),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
