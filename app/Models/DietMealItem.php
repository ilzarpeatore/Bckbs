<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DietMealItem extends Model
{
    protected $fillable = ['diet_id', 'meal_type', 'recipe_id', 'calories', 'protein', 'fats', 'carbs'];

    protected $casts = [
        'diet_id'   => 'integer',
        'recipe_id' => 'integer',
        'calories'  => 'double',
        'protein'   => 'double',
        'fats'      => 'double',
        'carbs'     => 'double',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'recipe_id', 'id');
    }

    public function diet()
    {
        return $this->belongsTo(Diet::class, 'diet_id', 'id');
    }
}
