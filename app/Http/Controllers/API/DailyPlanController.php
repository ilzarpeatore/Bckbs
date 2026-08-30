<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DailyPlan;
use App\Models\DailyPlanRecipe;
use App\Models\Recipe;
use App\Http\Resources\DailyPlanResource;
use App\Http\Resources\DailyPlanRecipeResource;
use App\Http\Resources\RecipePlanResource;
use Carbon\Carbon;

class DailyPlanController extends Controller
{
    public function recipeMealTypeResponse($daily_plan)
    {
        $recipe_meal_type = $daily_plan->meal_type;

        $daily_plan_recipe = [];

        $allRecipes = DailyPlanRecipe::planRecipeData([
            'daily_plan_id' => $daily_plan->id,
        ])->get()->groupBy('meal_type');

        // Total del dia = SOLO lo marcado como "comido" (is_complete = true).
        // Añadir una receta al plan es planificación, no consumo: por eso el
        // "Daily Total" (y el subtotal de cada sección, ver
        // getSumOfDailyPlanRecipe) deben contar únicamente is_complete=true,
        // igual que el subtotal por sección. Revertido el 2026-08-09: el
        // intento anterior (2026-08-02) de sumar TODO sin filtrar para que
        // "Daily Total" coincidiera con el subtotal era la causa real del bug
        // reportado por el usuario (kcal ya no bajaban al desmarcar/borrar
        // comidas sin marcar). La solución correcta es filtrar por
        // is_complete en ambos sitios, no dejar de filtrar en ninguno.
        $sumRow = DailyPlanRecipe::where('daily_plan_id', $daily_plan->id)
            ->where('is_complete', true)
            ->selectRaw('SUM(protein) as total_protein, SUM(fats) as total_fats, SUM(carbs) as total_carbs, SUM(calories) as total_calories')
            ->first();

        $totalProtein  = (float) ($sumRow->total_protein ?? 0);
        $totalFats     = (float) ($sumRow->total_fats ?? 0);
        $totalCarbs    = (float) ($sumRow->total_carbs ?? 0);
        $totalCalories = (float) ($sumRow->total_calories ?? 0);

        foreach ($recipe_meal_type as $meal_type) {
            $daily_plan_recipe[$meal_type] = DailyPlanRecipeResource::collection(
                $allRecipes->get($meal_type, collect())
            );
        }

        $daily_plan->update([
            'protein'   => $totalProtein,
            'fats'      => $totalFats,
            'carbs'     => $totalCarbs,
            'calories'  => $totalCalories,
            'eaten'     => $totalCalories,
            'left_eat'  => max(0, $daily_plan->daily_kcal - $totalCalories),
        ]);

        $daily_plan_date = Carbon::parse($daily_plan->date);

        $startOfWeek = $daily_plan_date->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek   = $daily_plan_date->copy()->endOfWeek(Carbon::SUNDAY);
        
        $day_has_daily_plan = DailyPlan::where('user_id', $daily_plan->user_id)->where('eaten', '!=', 0)
            ->whereBetween('date', [ $startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->pluck('date')->toArray();

        $response = [
            'data' => new DailyPlanResource($daily_plan),
            'daily_plan_recipe' => $daily_plan_recipe,
            'day_has_daily_plan' => $day_has_daily_plan,
        ];

        return $response;
    }

    public function saveDailyPlanRecipeData(Request $request)
    {
        $daily_plan = DailyPlan::myDailyPlan()->where('id', request('daily_plan_id'))->first();

        if( $daily_plan == null ) {
            return json_message_response( __('message.not_found_entry',['name' => __('message.daily_plan') ]), 400);
        }

        $recipe = Recipe::find(request('recipe_id'));
        if( $recipe == null ) {
            return json_message_response( __('message.not_found_entry',['name' => __('message.recipe') ]), 400);
        }

        // Scope any update-by-id to a row that belongs to this same daily plan,
        // so a caller can't pass someone else's DailyPlanRecipe id to hijack it.
        $existing = DailyPlanRecipe::where('id', request('id'))
            ->where('daily_plan_id', $daily_plan->id)
            ->first();

        $data = [
            'daily_plan_id' => $daily_plan->id,
            'recipe_id'     => request('recipe_id'),
            'meal_type'     => request('meal_type'),
            'is_complete'   => request('is_complete'),
            'calories'      => $recipe->calories,
            'protein'       => $recipe->protein,
            'fats'          => $recipe->fats,
            'carbs'         => $recipe->carbs,
        ];

        DailyPlanRecipe::updateOrCreate([ 'id' => $existing?->id ], $data);

        $response = $this->recipeMealTypeResponse($daily_plan);
        return $response;
    }

    public function getDailyPlanDetail(Request $request)
    {
        $date = request('date') ?? date('Y-m-d');

        $user = auth()->user();
        $daily_plan = DailyPlan::myDailyPlan()->whereDate('date', $date )->first();

        $calculate_daily_plan = DailyPlan::calculateDailyPlan($user);
        
        $daily_kcal = $calculate_daily_plan['kCal'];

        $meal_type = config('macro-nutrient.MEAL_TYPE');
        
        if( $daily_plan == null ) {
            $daily_plan_data = [
                'user_id'   => $user->id,
                'date'      => date('Y-m-d', strtotime($date)),
                'meal_type' => $meal_type,
                'daily_plan'=> $calculate_daily_plan,
                'daily_kcal'=> $daily_kcal,
            ];

            $result = DailyPlan::create($daily_plan_data);

        } else {
            $daily_plan_data = [
                'user_id' => $user->id,
                'date' => date('Y-m-d', strtotime($date)), 
            ];
            $result = DailyPlan::updateOrCreate([ 'id' => $daily_plan->id ], $daily_plan_data);
        }

        $response = $this->recipeMealTypeResponse($result);

        return $response;
    }

    /**
     * Everything ever assigned to the authenticated client across their whole
     * calendar, grouped by meal_type and de-duplicated by recipe (used by the
     * "Assigned to Me" screen — a pooled view of the calendar, not tied to any
     * single date or legacy Diet record).
     */
    public function getAssignedMealsSummary(Request $request)
    {
        $user = auth()->user();

        $goal = DailyPlan::calculateDailyPlan($user);

        $recipeEntries = DailyPlanRecipe::whereHas('dailyplan', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with('recipe')
            ->get()
            ->filter(fn ($item) => $item->recipe !== null)
            ->unique(fn ($item) => $item->meal_type . '-' . $item->recipe_id)
            ->groupBy('meal_type');

        $meals = [];
        foreach (config('macro-nutrient.MEAL_TYPE') as $mealType) {
            $meals[$mealType] = ($recipeEntries->get($mealType) ?? collect())
                ->values()
                ->map(fn ($item) => new RecipePlanResource($item->recipe))
                ->all();
        }

        return json_custom_response([
            'goal' => [
                'kcal'    => round($goal['kCal'] ?? 0),
                'protein' => $goal['protein']['target'] ?? 0,
                'carbs'   => $goal['carbs']['target'] ?? 0,
                'fats'    => $goal['fat']['target'] ?? 0,
            ],
            'meals' => $meals,
        ]);
    }

    public function deleteDailyPlan(Request $request)
    {
        $daily_plan = DailyPlan::myDailyPlan()->where('id', request('id'))->first();

        $message = __('message.not_found_entry', [ 'name' => __('message.daily_plan') ]);

        if( $daily_plan != null ) {
            $daily_plan->delete();
            $message = __('message.delete_form', [ 'form' => __('message.daily_plan') ]);
        }

        return json_message_response($message);
    }

    public function deleteDailyPlanRecipeData(Request $request)
    {
        $daily_plan_recipe = DailyPlanRecipe::with('dailyplan')
            ->whereHas('dailyplan', function ($q) {
                $q->myDailyPlan();
            })
            ->find(request('id'));

        if ($daily_plan_recipe == null) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.daily_plan_recipe')]));
        }

        $daily_plan = $daily_plan_recipe->dailyplan;
        $daily_plan_recipe->delete();

        if ($daily_plan != null) {
            return $this->recipeMealTypeResponse($daily_plan);
        }

        return json_message_response(__('message.delete_form', ['name' => __('message.daily_plan_recipe')]));
    }

    public function deleteDailyPlanRecipeAllData(Request $request)
    {
        $daily_plan = DailyPlan::myDailyPlan()->where('id', request('daily_plan_id'))->first();

        if (!$daily_plan) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.daily_plan')]));
        }

        $deleted = DailyPlanRecipe::planRecipeData([
            'daily_plan_id' => $daily_plan->id,
            'meal_type'     => request('meal_type'),
        ])->delete();

        if ($deleted) {
            return $this->recipeMealTypeResponse($daily_plan);
        }

        return json_message_response(__('message.not_found_entry', ['name' => __('message.daily_plan_recipe')]));
    }

}
