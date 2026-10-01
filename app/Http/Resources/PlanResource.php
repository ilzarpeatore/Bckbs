<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'price' => $this->price,
            'signup_fee' => $this->signup_fee,
            'currency' => $this->currency,
            'trial_period' => $this->trial_period,
            'trial_interval' => $this->trial_interval,
            'invoice_period' => $this->invoice_period,
            'invoice_interval' => $this->invoice_interval,
            'grace_period' => $this->grace_period,
            'grace_interval' => $this->grace_interval,
            'prorate_day' => $this->prorate_day,
            'prorate_period' => $this->prorate_period,
            'prorate_extend_due' => $this->prorate_extend_due,
            'active_subscribers_limit' => $this->active_subscribers_limit,
            'sort_order' => $this->sort_order,
            'training_program_id' => $this->training_program_id,
            'meal_plan_template_id' => $this->meal_plan_template_id,
            'grants_full_workout_library' => $this->grants_full_workout_library,
            'grants_full_recipe_library' => $this->grants_full_recipe_library,
            'is_pack' => (bool) $this->is_pack,
            'sold_on_web' => (bool) $this->sold_on_web,
            'pack_url' => $this->packUrl(),
            'short_description' => $this->short_description,
            'image_url' => $this->image_url,
            'habit_template_ids' => $this->habit_template_ids ?? [],
            'resource_ids' => $this->resource_ids ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
