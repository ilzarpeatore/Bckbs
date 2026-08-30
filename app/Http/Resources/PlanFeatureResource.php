<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanFeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'value' => $this->value,
            'resettable_period' => $this->resettable_period,
            'resettable_interval' => $this->resettable_interval,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
