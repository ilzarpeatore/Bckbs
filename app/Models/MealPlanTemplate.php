<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealPlanTemplate extends Model
{
    protected $fillable = ['title', 'type', 'coach_id'];

    public function items()
    {
        return $this->hasMany(MealPlanTemplateItem::class, 'meal_plan_template_id', 'id');
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }
}
