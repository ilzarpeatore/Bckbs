<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealPlanTemplateItem extends Model
{
    // fatsecret_recipe_id (2026-09-20): exactamente uno de recipe_id /
    // fatsecret_recipe_id debe estar relleno, igual que en DailyPlanRecipe.
    protected $fillable = ['meal_plan_template_id', 'day_key', 'meal_type', 'recipe_id', 'fatsecret_recipe_id', 'calories', 'protein', 'fats', 'carbs'];

    protected $casts = [
        'meal_plan_template_id' => 'integer',
        'recipe_id'             => 'integer',
        'fatsecret_recipe_id'   => 'integer',
        'calories'              => 'double',
        'protein'               => 'double',
        'fats'                  => 'double',
        'carbs'                 => 'double',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'recipe_id', 'id');
    }

    public function template()
    {
        return $this->belongsTo(MealPlanTemplate::class, 'meal_plan_template_id', 'id');
    }
}
