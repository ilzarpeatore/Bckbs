<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingDetailResource extends JsonResource
{
    public function toArray($request)
    {
        $items = $this->relationLoaded('items') ? $this->items : collect();

        $payload = [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'daily_plan_id' => $this->daily_plan_id,
            'title'         => $this->title,
            'start_date'    => $this->start_date,
            'end_date'      => $this->end_date,
            'servings'      => (float) ($this->servings ?? 1),
            'status'        => $this->status,
            'items_count'   => isset($this->items_count) ? (int) $this->items_count : $this->items()->count(),
            'items'         => ShoppingListItemResource::collection($items),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];

        if ($items->isNotEmpty()) {
            $grouped = $items->groupBy(function ($item) {
                return optional(optional($item->ingredient)->ingredientcategory)->id;
            });

            $categoryGroups = $grouped->map(function ($groupItems, $categoryId) {
                $categoryTitle = null;
                $first = $groupItems->first();
                if ($first && $first->ingredient && $first->ingredient->ingredientcategory) {
                    $categoryTitle = $first->ingredient->ingredientcategory->title;
                }

                if ($categoryId === null) {
                    $categoryTitle = 'Other';
                }

                return [
                    'ingredient_category_id' => $categoryId,
                    'ingredient_category_title' => $categoryTitle,
                    'items' => ShoppingListItemResource::collection($groupItems),
                ];
            });

            $payload['items_by_category'] = $categoryGroups->sort(function ($a, $b) {
                if ($a['ingredient_category_id'] === null) return 1;
                if ($b['ingredient_category_id'] === null) return -1;
                return strcmp((string) $a['ingredient_category_title'], (string) $b['ingredient_category_title']);
            })->values()->toArray();
        } else {
            $payload['items_by_category'] = [];
        }

        return $payload;
    }
}
