<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\DailyPlanTrait;

class DailyPlan extends Model
{
    use DailyPlanTrait;
    
    protected $fillable = [ 'user_id', 'date', 'eaten', 'left_eat', 'daily_kcal', 'calories', 'protein', 'fats', 'carbs', 'daily_plan', 'meal_type' ];

     protected $casts = [
        'user_id'   => 'integer',
        'eaten'     => 'double',
        'left_eat'  => 'double',
        'daily_kcal'=> 'double',
        'calories'  => 'double',
        'protein'   => 'double',
        'fats'      => 'double',
        'carbs'     => 'double',
    ];

    public function getMealTypeAttribute($value)
    {
        return isset($value) ? json_decode($value, true) : null; 
    }

    public function setMealTypeAttribute($value)
    {
        $this->attributes['meal_type'] = isset($value) ? json_encode($value) : null;
    }

    public function getDailyPlanAttribute($value)
    {
        return isset($value) ? json_decode($value, true) : null; 
    }

    public function setDailyPlanAttribute($value)
    {
        $this->attributes['daily_plan'] = isset($value) ? json_encode($value) : null;
    }

    public function dailyPlanRecipe()
    {
        return $this->hasMany(DailyPlanRecipe::class, 'daily_plan_id', 'id');
    }

    public function scopeMyDailyPlan($query)
    {
        $user = auth()->user();

        if( isset($user) && $user->hasRole(['user']) ) {
            $query = $query->where('user_id', $user->id);
        }

        return $query;
    }

    public function getCalculateDailyPlanAttribute()
    {
        $data = $this->daily_plan;
        $protein = $this->protein ?? 0;
        $carbs = $this->carbs ?? 0;
        $fats = $this->fats ?? 0;

        if( $data != null ) {
            $data['protein'] = [
                'from' => max(0, round($data['protein']['from'] - $protein)),
                'to' => max(0,round($data['protein']['to'] - $protein)),
            ];

            $data['carbs'] = [
                'from' => max(0,  round($data['carbs']['from'] - $carbs)),
                'to' => max(0, round($data['carbs']['to'] - $carbs)),
            ];

            $data['fat'] = [
                'from' => max(0, round($data['fat']['from'] - $fats)),
                'to' => max(0, round($data['fat']['to'] - $fats)),
            ];
        }

        $data['kCal'] = $this->daily_kcal - $this->calories;
        return $data;
    }

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($row) {
            $row->dailyPlanRecipe()->delete();
        });
    }
    
}
