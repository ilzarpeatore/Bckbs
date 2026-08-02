<?php

namespace App\Traits;
use App\Models\DailyPlan;
use App\Models\DailyPlanRecipe;

trait DailyPlanTrait
{

    public static function calculateDailyPlan($user)
    {
        $user_activity = optional($user->userProfile)->activity ?? 'sedentary';
        $user_goal = optional($user->userProfile)->goal ?? 'm';
        $user_macro_type = optional($user->userProfile)->macro_type ?? 'balanced';

        // BMR (Mifflin-St Jeor)
        $bmr = optional($user->userProfile)->bmr;

        // TDEE
        $tdee = $bmr * config('macro-nutrient.ACTIVITY_LEVEL.'.$user_activity);
        
        $target_calories = round( $tdee * (1 + config('macro-nutrient.FITNESS_GOAL.'.$user_goal) ));

        if( $user_macro_type == 'custom' ) {
            $macro_ratio = [
                'protein'   => optional($user->userProfile)->protein_pct ?? 0,
                'carbs'     => optional($user->userProfile)->carbs_pct ?? 0,
                'fat'       => optional($user->userProfile)->fat_pct ?? 0,
            ];
        } else {
            $macro_ratio = config('macro-nutrient.MACRO_RATIO.'.$user_macro_type);
        }
        
        $result = [];

        foreach (['protein' => 4, 'fat' => 9, 'carbs' => 4] as $key => $cal_per_g) {
            $target_g = ($target_calories * ( $macro_ratio[$key] / 100 )) / $cal_per_g;
            $result[$key] = [
                'target'=> round($target_g),
                'from'  => round($target_g * 0.90), // -10%
                'to'    => round($target_g * 1.10)  // +10%
            ];
        }
        $result['age'] = optional($user->userProfile)->age ?? 0;
        $result['goal'] = $user_goal;
        $result['activity'] = $user_activity;
        $result['macro_type'] = $user_macro_type;
        $result['weight_in_kg'] = optional($user->userProfile)->weight_in_kg ?? 0;
        $result['height_in_cm'] = optional($user->userProfile)->height_in_cm ?? 0;
        $result['bmr'] = $bmr;
        $result['kCal'] = $target_calories;
        $result['kCal_from'] = ($target_calories * 0.9);// -10%
        $result['kCal_to'] = ($target_calories * 1.1);  // +10%
        
        return $result;
    }

    public static function getSumOfDailyPlanRecipe($daily_plan)
    {
        return collect($daily_plan->meal_type)->map(function ($meal) use ($daily_plan)
        {
            $query_data = [
                'daily_plan_id' => $daily_plan->id,
                'meal_type'     => $meal,
            ];
            
            $daily_plan_recipe_sum = DailyPlanRecipe::sumProtienCarbsFatsCalories()->planRecipeData($query_data)->first();

            return [
                'key'          => $meal,
                'display_name' => __('message.' . $meal),
                'total' => [
                    'total_calories' => round((float) $daily_plan_recipe_sum->total_calories),
                    'total_protein'  => round((float) $daily_plan_recipe_sum->total_protein),
                    'total_carbs'    => round((float) $daily_plan_recipe_sum->total_carbs),
                    'total_fats'     => round((float) $daily_plan_recipe_sum->total_fats),
                ],
            ];
        });
    }

    /**
     * Find (or create, with the client's calculated goal) the DailyPlan row for a
     * given user + date. Shared by the admin meal-calendar and meal-plan-template
     * import flows, both of which write to a client's plan on their behalf.
     */
    public static function findOrCreateDailyPlan($user, string $date): DailyPlan
    {
        $daily_plan = DailyPlan::where('user_id', $user->id)->whereDate('date', $date)->first();

        if ($daily_plan != null) {
            return $daily_plan;
        }

        $calculate_daily_plan = self::calculateDailyPlan($user);

        return DailyPlan::create([
            'user_id'    => $user->id,
            'date'       => date('Y-m-d', strtotime($date)),
            'meal_type'  => config('macro-nutrient.MEAL_TYPE'),
            'daily_plan' => $calculate_daily_plan,
            'daily_kcal' => $calculate_daily_plan['kCal'],
        ]);
    }

    public static function resetDailyPlan($user, $date = null)
    {
        
        $daily_plan = DailyPlan::where('user_id', $user->id);
        
        if( $date != null ) {
            $daily_plan = $daily_plan->whereDate('date', $date)->get();
        } else {
            $daily_plan = $daily_plan->whereDate('date', '>=', date('Y-m-d') )->get();
        }
            
        if( count($daily_plan) > 0 ) {
            foreach ($daily_plan as $plan) {

                $calculate_daily_plan = self::calculateDailyPlan($user);
                $daily_kcal = $calculate_daily_plan['kCal'];
                
                $meal_type = config('macro-nutrient.MEAL_TYPE');

                $daily_plan_data = [
                    'user_id'       => $user->id,
                    'date'          => date('Y-m-d', strtotime($plan->date)),
                    'meal_type'     => $meal_type,
                    'daily_plan'    => $calculate_daily_plan,
                    'daily_kcal'    => $daily_kcal,
                ];
                $result = DailyPlan::updateOrCreate([ 'id' => $plan->id ], $daily_plan_data);
            }
        }
        return;
    }
}