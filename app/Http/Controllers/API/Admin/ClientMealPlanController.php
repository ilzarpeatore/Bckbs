<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Models\DailyPlanRecipe;
use App\Models\Recipe;
use App\Models\User;
use App\Http\Resources\DailyPlanRecipeResource;
use Carbon\Carbon;

class ClientMealPlanController extends Controller
{
    public function getCalendar(Request $request)
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end = Carbon::parse($request->end_date)->startOfDay();

        if ($start->diffInDays($end) > 62) {
            return json_message_response('Date range too large (max 62 days).', 400);
        }

        $plans = DailyPlan::where('user_id', $request->user_id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with(['dailyPlanRecipe' => function ($q) {
                $q->with('recipe', 'assignedBy');
            }])
            ->get()
            ->keyBy(fn ($plan) => Carbon::parse($plan->date)->toDateString());

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dateKey = $day->toDateString();
            $plan = $plans->get($dateKey);

            $meals = [];
            foreach (config('macro-nutrient.MEAL_TYPE') as $mealType) {
                $meals[$mealType] = $plan
                    ? DailyPlanRecipeResource::collection(
                        $plan->dailyPlanRecipe->where('meal_type', $mealType)->values()
                    )
                    : [];
            }

            $days[] = [
                'date'          => $dateKey,
                'daily_plan_id' => $plan?->id,
                'meals'         => $meals,
            ];
        }

        return json_custom_response(['data' => $days]);
    }

    public function assignRecipe(Request $request)
    {
        $request->validate([
            'user_id'   => 'required|exists:users,id',
            'date'      => 'required|date',
            'meal_type' => 'required|in:' . implode(',', config('macro-nutrient.MEAL_TYPE')),
            'recipe_id' => 'required|exists:recipes,id',
        ]);

        $user = User::find($request->user_id);
        $recipe = Recipe::find($request->recipe_id);

        $daily_plan = DailyPlan::findOrCreateDailyPlan($user, $request->date);

        $daily_plan_recipe = DailyPlanRecipe::create([
            'daily_plan_id'       => $daily_plan->id,
            'recipe_id'           => $recipe->id,
            'meal_type'           => $request->meal_type,
            'calories'            => $recipe->calories,
            'protein'             => $recipe->protein,
            'fats'                => $recipe->fats,
            'carbs'               => $recipe->carbs,
            'assigned_by_user_id' => auth()->id(),
        ]);

        return json_custom_response([
            'message' => 'Recipe assigned successfully.',
            'data'    => new DailyPlanRecipeResource($daily_plan_recipe->load('recipe', 'assignedBy')),
        ], 201);
    }

    public function removeAssignedRecipe($id)
    {
        $daily_plan_recipe = DailyPlanRecipe::find($id);

        if (!$daily_plan_recipe) {
            return json_message_response('Assigned meal not found.', 404);
        }

        $daily_plan_recipe->delete();

        return json_message_response('Assigned meal removed.');
    }
}
