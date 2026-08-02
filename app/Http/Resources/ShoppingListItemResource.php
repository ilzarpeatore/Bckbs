<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'shopping_list_id'  => $this->shopping_list_id,
            'ingredient_id'     => $this->ingredient_id,
            'ingredient_title'  => optional($this->ingredient)->title,
            'ingredient_category_id' => optional(optional($this->ingredient)->ingredientcategory)->id,
            'ingredient_category_title' => optional(optional($this->ingredient)->ingredientcategory)->title,
            'custom_item_name'  => $this->custom_item_name,
            'total_grams'       => $this->total_grams === null ? null : round((float) $this->total_grams, 2),
            'display_quantity'  => round((float) ($this->display_quantity ?? 0), 2),
            'measurement_unit_id'   => $this->measurement_unit_id,
            'display_unit_title'    => optional($this->measurementUnit)->title,
            'display_unit_symbol'   => optional($this->measurementUnit)->symbol,
            'is_checked'        => (bool) $this->is_checked,
            'manually_added'    => (bool) $this->manually_added,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
