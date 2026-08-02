<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanTemplateResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'type'       => $this->type,
            'items_count'=> $this->items_count ?? $this->items()->count(),
            'items'      => MealPlanTemplateItemResource::collection($this->whenLoaded('items')),
            'coach'      => $this->whenLoaded('coach', fn () => [
                'id'   => $this->coach->id,
                'name' => $this->coach->display_name ?: $this->coach->email,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
