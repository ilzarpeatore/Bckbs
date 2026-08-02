<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
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
            'age'           => $this->age,
            'height'        => $this->height,
            'height_unit'   => $this->height_unit,
            'weight'        => $this->weight,
            'weight_unit'   => $this->weight_unit,
            'address'       => $this->address,
            'user_id'       => $this->user_id,
            'bmi'           => $this->bmi,
            'bmr'           => $this->bmr,
            'ideal_weight'  => $this->ideal_weight,
            'activity'      => $this->activity,
            'goal'          => $this->goal,
            'macro_type'    => $this->macro_type,
            'carbs_pct'     => $this->carbs_pct,
            'fat_pct'       => $this->fat_pct,
            'protein_pct'   => $this->protein_pct,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'water_reminder_settings' => $this->water_reminder_settings,
            'meal_reminder_settings'  => $this->meal_reminder_settings,
        ];
    }
}
