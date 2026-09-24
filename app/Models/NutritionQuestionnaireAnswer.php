<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NutritionQuestionnaireAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'allergies_intolerances',
        'medications',
        'supplements',
        'disliked_foods',
        'liked_foods',
        'current_meals_per_day',
        'desired_meals_per_day',
        'typical_day_meals',
        'favorite_meats',
        'favorite_fish',
        'favorite_fruits_vegetables',
        'favorite_combined_dishes',
        'cooking_minutes_per_meal',
        'cooking_skill_level',
        'cooks_for_others',
    ];

    protected $casts = [
        'current_meals_per_day'    => 'integer',
        'desired_meals_per_day'    => 'integer',
        'cooking_minutes_per_meal' => 'integer',
        'cooks_for_others'         => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
