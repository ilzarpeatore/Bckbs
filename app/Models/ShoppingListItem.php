<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShoppingListItem extends Model
{
    protected $fillable = [
        'shopping_list_id', 'ingredient_id', 'custom_item_name', 'total_grams', 'display_quantity', 'measurement_unit_id', 'is_checked', 'manually_added',
    ];

    protected $casts = [
        'shopping_list_id'  => 'integer',
        'ingredient_id'     => 'integer',
        'measurement_unit_id'   => 'integer',
        'total_grams'       => 'double',
        'display_quantity'  => 'double',
        'is_checked'        => 'boolean',
        'manually_added'    => 'boolean',
    ];

    public function shoppingList()
    {
        return $this->belongsTo(ShoppingList::class, 'shopping_list_id', 'id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'id');
    }

    public function measurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_id', 'id');
    }
}
