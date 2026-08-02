<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource  extends JsonResource
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
            'id'                  => $this->id,
            'name'                => $this->name,
            'duration'            => $this->duration,
            'duration_unit'       => $this->duration_unit,
            'price'               => $this->price,
            'description'         => $this->description,
            'status'              => $this->status,
            'training_program_id'    => $this->training_program_id,
            'training_program_title' => optional($this->trainingProgram)->title,
            'meal_plan_template_id'    => $this->meal_plan_template_id,
            'meal_plan_template_title' => optional($this->mealPlanTemplate)->title,
            'grants_full_workout_library' => (bool) $this->grants_full_workout_library,
            'grants_full_recipe_library'  => (bool) $this->grants_full_recipe_library,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}