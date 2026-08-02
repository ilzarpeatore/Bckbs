<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\MealPlanTemplate;
use App\Models\MealPlanTemplateItem;
use App\Models\DailyPlan;
use App\Models\DailyPlanRecipe;
use App\Models\Recipe;
use App\Models\User;
use App\Http\Resources\MealPlanTemplateResource;
use App\Http\Resources\MealPlanTemplateItemResource;
use Carbon\Carbon;

class MealPlanTemplateController extends Controller
{
    private function mealTypes(): array
    {
        return config('macro-nutrient.MEAL_TYPE');
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        if ($perPage == -1 || $perPage > 250) {
            $perPage = 250;
        }
        $templates = MealPlanTemplate::withCount('items')->orderBy('id', 'desc')->paginate($perPage);

        return json_custom_response([
            'pagination' => json_pagination_response($templates),
            'data'       => MealPlanTemplateResource::collection($templates),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type'  => 'required|in:sequential,weekday',
        ]);

        $template = MealPlanTemplate::create([
            'title'    => $request->title,
            'type'     => $request->type,
            'coach_id' => auth()->id(),
        ]);

        return json_custom_response(['data' => new MealPlanTemplateResource($template)], 201);
    }

    public function show($id)
    {
        $template = MealPlanTemplate::with(['items.recipe', 'coach'])->find($id);

        if (!$template) {
            return json_message_response('Template not found.', 404);
        }

        return json_custom_response(['data' => new MealPlanTemplateResource($template)]);
    }

    public function destroy($id)
    {
        $template = MealPlanTemplate::find($id);

        if (!$template) {
            return json_message_response('Template not found.', 404);
        }

        $template->delete();

        return json_message_response('Template deleted.');
    }

    public function addItem(Request $request, $id)
    {
        $template = MealPlanTemplate::find($id);

        if (!$template) {
            return json_message_response('Template not found.', 404);
        }

        $request->validate([
            'day_key'   => 'required|string',
            'meal_type' => 'required|in:' . implode(',', $this->mealTypes()),
            'recipe_id' => 'required|exists:recipes,id',
        ]);

        $recipe = Recipe::find($request->recipe_id);

        $item = MealPlanTemplateItem::create([
            'meal_plan_template_id' => $template->id,
            'day_key'               => $request->day_key,
            'meal_type'             => $request->meal_type,
            'recipe_id'             => $recipe->id,
            'calories'              => $recipe->calories,
            'protein'               => $recipe->protein,
            'fats'                  => $recipe->fats,
            'carbs'                 => $recipe->carbs,
        ]);

        return json_custom_response([
            'message' => 'Item added.',
            'data'    => new MealPlanTemplateItemResource($item->load('recipe')),
        ], 201);
    }

    public function removeItem($itemId)
    {
        $item = MealPlanTemplateItem::find($itemId);

        if (!$item) {
            return json_message_response('Item not found.', 404);
        }

        $item->delete();

        return json_message_response('Item removed.');
    }

    /**
     * Copy a real date range from a client's calendar into this template's items,
     * replacing whatever the template already had. For 'sequential' templates the
     * first date in the range becomes day 0. For 'weekday' templates, each weekday
     * takes its first occurrence in the range (later repeats of the same weekday
     * in a long range are skipped, not merged).
     */
    public function exportFromCalendar(Request $request, $id)
    {
        $template = MealPlanTemplate::find($id);

        if (!$template) {
            return json_message_response('Template not found.', 404);
        }

        $request->validate([
            'client_id'  => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end = Carbon::parse($request->end_date)->startOfDay();

        if ($start->diffInDays($end) > 62) {
            return json_message_response('Date range too large (max 62 days).', 400);
        }

        $plans = DailyPlan::where('user_id', $request->client_id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('dailyPlanRecipe')
            ->get()
            ->keyBy(fn ($plan) => Carbon::parse($plan->date)->toDateString());

        $template->items()->delete();

        $seenWeekdays = [];
        $offset = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay(), $offset++) {
            $dateKey = $day->toDateString();
            $plan = $plans->get($dateKey);
            if (!$plan) {
                continue;
            }

            if ($template->type === 'sequential') {
                $dayKey = (string) $offset;
            } else {
                $weekday = strtolower($day->format('l'));
                if (in_array($weekday, $seenWeekdays)) {
                    continue;
                }
                $seenWeekdays[] = $weekday;
                $dayKey = $weekday;
            }

            foreach ($plan->dailyPlanRecipe as $recipeEntry) {
                MealPlanTemplateItem::create([
                    'meal_plan_template_id' => $template->id,
                    'day_key'               => $dayKey,
                    'meal_type'             => $recipeEntry->meal_type,
                    'recipe_id'             => $recipeEntry->recipe_id,
                    'calories'              => $recipeEntry->calories,
                    'protein'               => $recipeEntry->protein,
                    'fats'                  => $recipeEntry->fats,
                    'carbs'                 => $recipeEntry->carbs,
                ]);
            }
        }

        return json_custom_response([
            'message' => 'Template updated from calendar.',
            'data'    => new MealPlanTemplateResource($template->load('items.recipe')),
        ]);
    }

    /**
     * Apply this template onto a client's real calendar starting at start_date.
     * 'sequential' templates lay out day 0..maxOffset from start_date. 'weekday'
     * templates repeat their Mon..Sun pattern for the given number of weeks.
     */
    public function importToCalendar(Request $request, $id)
    {
        $template = MealPlanTemplate::with('items')->find($id);

        if (!$template) {
            return json_message_response('Template not found.', 404);
        }

        $request->validate([
            'client_id'  => 'required|exists:users,id',
            'start_date' => 'required|date',
            'weeks'      => 'nullable|integer|min:1|max:12',
        ]);

        $user = User::find($request->client_id);
        $start = Carbon::parse($request->start_date)->startOfDay();
        $created = 0;

        if ($template->type === 'sequential') {
            $itemsByOffset = $template->items->groupBy('day_key');

            foreach ($itemsByOffset as $dayKey => $items) {
                $date = $start->copy()->addDays((int) $dayKey)->toDateString();
                $dailyPlan = DailyPlan::findOrCreateDailyPlan($user, $date);

                foreach ($items as $item) {
                    DailyPlanRecipe::create([
                        'daily_plan_id'       => $dailyPlan->id,
                        'recipe_id'           => $item->recipe_id,
                        'meal_type'           => $item->meal_type,
                        'calories'            => $item->calories,
                        'protein'             => $item->protein,
                        'fats'                => $item->fats,
                        'carbs'               => $item->carbs,
                        'assigned_by_user_id' => auth()->id(),
                    ]);
                    $created++;
                }
            }
        } else {
            $weeks = $request->weeks ?? 1;
            $itemsByWeekday = $template->items->groupBy('day_key');
            $totalDays = $weeks * 7;

            for ($i = 0; $i < $totalDays; $i++) {
                $day = $start->copy()->addDays($i);
                $weekday = strtolower($day->format('l'));
                $items = $itemsByWeekday->get($weekday);
                if (!$items) {
                    continue;
                }

                $dailyPlan = DailyPlan::findOrCreateDailyPlan($user, $day->toDateString());

                foreach ($items as $item) {
                    DailyPlanRecipe::create([
                        'daily_plan_id'       => $dailyPlan->id,
                        'recipe_id'           => $item->recipe_id,
                        'meal_type'           => $item->meal_type,
                        'calories'            => $item->calories,
                        'protein'             => $item->protein,
                        'fats'                => $item->fats,
                        'carbs'               => $item->carbs,
                        'assigned_by_user_id' => auth()->id(),
                    ]);
                    $created++;
                }
            }
        }

        return json_custom_response(['message' => "Template imported ({$created} meals assigned)."]);
    }
}
