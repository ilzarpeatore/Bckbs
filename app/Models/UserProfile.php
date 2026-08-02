<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCircleProgress;
use App\Traits\DailyPlanTrait;

class UserProfile extends Model
{
    use HasFactory;
    use HasCircleProgress, DailyPlanTrait;

    protected $fillable = [ 'user_id', 'age', 'height', 'height_unit', 'weight', 'weight_unit', 'address', 'activity', 'goal', 'macro_type', 'protein_pct', 'carbs_pct', 'fat_pct', 'water_reminder_settings', 'meal_reminder_settings' ];

    protected $casts = [
        'user_id'   => 'integer',
        'carbs_pct' => 'integer',
        'fat_pct'   => 'integer',
        'protein_pct'=> 'integer',
        'water_reminder_settings'   => 'array',
        'meal_reminder_settings'    => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getBmiAttribute()
    {
        $height = $this->height;
        $height_unit = $this->height_unit;
        $weight = $this->weight;
        $weight_unit = $this->weight_unit;

        if( $height || $height_unit || $weight || $weight_unit ) 
        {
            // Convert weight to kilograms if unit is not 'kg'
            if ($weight_unit !== 'kg') {
                if ($weight_unit === 'lbs') {
                    $weight = $weight * 0.453592; // Convert pounds to kilograms
                } else {
                    return  null;
                }
            }

            // Convert height to meters based on unit
            switch ($height_unit) {
                case 'cm':
                    $height = $height / 100; // Convert centimeters to meters
                    break;
                case 'feet':
                    $height = $height * 0.3048; // Convert feet to meters
                    break;
                case 'in':
                    $height = $height * 0.0254; // Convert inches to meters
                    break;
                default:
                    return null;
            }

            // Calculate BMI
            $bmi = $weight / ($height * $height);
            return number_format( (float) $bmi, 2,'.','');
        }
    }

    public function getBmrAttribute()
    {
        $male_constant = 5;
        $female_constant = 161;
        $height_constant = 6.25;
        $weight_constant = 10;

        $age = $this->age;
        $gender = optional($this->user)->gender;
        $height_in_cm = $this->height_in_cm;
        $weight_in_kg = $this->weight_in_kg;
        
        $bmr = ( $weight_constant * $weight_in_kg ) + ( $height_constant * $height_in_cm );
        // Calculate BMR based on gender
        if( $gender == 'male' )
        {
            $bmr = $bmr - (5 * $age) + $male_constant;
        } elseif ( $gender == 'female' || $gender == 'other' ) {
            
            $bmr =  $bmr - (5 * $age) - $female_constant;
        } else {
            return null; // "Invalid gender. Please specify 'male' or 'female'.";
        }
        return number_format( (float) $bmr, 2,'.','');
    }

    public function getIdealWeightAttribute()
    {
        $height = $this->height;
        $weight = $this->weight;
        $height_unit = $this->height_unit;
        $weight_unit = $this->weight_unit;
        $gender = optional($this->user)->gender;

        $height_inches = 0;
        // Convert height to inches
        switch ($height_unit) {
            case 'cm':
                $height_inches = $height / 2.54;
                break;
            case 'feet':
                $height_inches = $height * 12;
                break;
            default:
                return null;
        }
        // return $height_inches;
        $base_weight = $gender == 'male' ? 52 : 49; // Base weight in kg

        $weight_per_inch = $gender == 'male' ? 1.9 : 1.7; // Additional weight per inch in kg

        $ideal_weight = $base_weight + ( $weight_per_inch * ( $height_inches - 60));
        
        return number_format( (float) $ideal_weight, 2,'.','');
    }

    public function dailyStepsGoals()
    {
        return $this->hasMany(DailyStepsGoal::class, 'user_id', 'user_id');
    }

    public function dailyWaterGoals()
    {
        return $this->hasMany(DailyWaterGoal::class, 'user_id', 'user_id');
    }

    public function getTodayDailyStepsValueAttribute()
    {
        return $this->dailyStepsGoals()->whereDate('created_at', today())->latest()->value('value');
    }

    public function getTodayDailyWaterValueAttribute()
    {
        return $this->dailyWaterGoals()->whereDate('created_at', today())->latest()->value('value');
    }

    public function userGraph()
    {
        return $this->hasMany(UserGraph::class, 'user_id', 'user_id');
    }

    public function getTodayWaterStepsGoalAttribute()
    {
        return $this->userGraph()->whereDate('created_at', today())->latest()->pluck('value', 'type');
    }

    public function getStepCircleAttribute()
    {
        return $this->getCircleProgress(
            $this->today_daily_steps_value ?? 0,
            $this->today_water_steps_goal['step_track'] ?? 0,
        );
    }
    
    public function getWaterCircleAttribute()
    {
        return $this->getCircleProgress(
            $this->today_daily_water_value ?? 0,
            $this->today_water_steps_goal['water_track'] ?? 0,
        );
    }

    public function getWeightInKGAttribute()
    {
        if ( $this->weight_unit == 'lbs' ) {
            return $this->weight * 0.453592;
        }
        return $this->weight;
    }

    public function getHeightInCMAttribute()
    {
        if ( $this->height_unit == 'feet' ) {
            return $this->height * 30.48;
        }
        return $this->height;
    }
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($row) {
            if ($row->macro_type !== 'custom') {
                $row->updateQuietly([
                    'carbs_pct'   => 0,
                    'fat_pct'     => 0,
                    'protein_pct' => 0,
                ]);
            }
            if ( ($row->wasChanged(['goal', 'activity_level', 'macro_type'])) ||
                ($row->macro_type == 'custom' && $row->wasChanged(['carbs_pct', 'fat_pct', 'protein_pct']) )
            )
            {
                $row->refresh();
                self::resetDailyPlan($row->user);
            }
        });
    }
}
