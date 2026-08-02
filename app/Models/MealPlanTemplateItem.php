<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealPlanTemplateItem extends Model
{
    protected $fillable = ['meal_plan_template_id', 'day_key', 'meal_type', 'recipe_id', 'calories', 'protein', 'fats', 'carbs'];

    protected $casts = [
        'meal_plan_template_id' => 'integer',
        'recipe_id'             => 'integer',
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
