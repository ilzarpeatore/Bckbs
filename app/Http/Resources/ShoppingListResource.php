<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'daily_plan_id' => $this->daily_plan_id,
            'title'         => $this->title,
            'start_date'    => $this->start_date,
            'end_date'      => $this->end_date,
            'servings'      => (float) ($this->servings ?? 1),
            'status'        => $this->status,
            'items_count'   => isset($this->items_count) ? (int) $this->items_count : $this->items()->count(),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
