<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\DailyPlanTrait;

class DailyPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $meal_type = DailyPlanTrait::getSumOfDailyPlanRecipe($this);
        
        return [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'date'          => $this->date,
            'eaten'         => $this->eaten ?? 0,
            'left_eat'      => $this->left_eat ?? 0,
            'daily_kcal'    => $this->daily_kcal,
            'calories'      => round($this->calories),
            'protein'       => round($this->protein),
            'fats'          => round($this->fats),
            'carbs'         => round($this->carbs),
            'daily_plan'    => $this->daily_plan,
            'meal_type'     => $meal_type,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
