<?php

namespace App\Services;

use App\Models\DailyPlan;
use App\Models\DailyPlanRecipe;
use App\Models\IngredientUnitConversion;
use App\Models\MeasurementUnit;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DailyPlanShoppingListService
{
    public function generate(User $user, array $filters, ?ShoppingList $refreshList = null): ShoppingList
    {
        $servings = (float) ($filters['servings'] ?? ($refreshList->servings ?? 1));
        if ($servings <= 0) {
            $servings = 1;
        }
        $dailyPlanIds = $this->resolveDailyPlanIds($user, $filters);

        $recipesQuery = DailyPlanRecipe::with(['recipe.recipeIngredients'])
            ->whereIn('daily_plan_id', $dailyPlanIds);

        if (!empty($filters['meal_types']) && is_array($filters['meal_types'])) {
            $recipesQuery->whereIn('meal_type', $filters['meal_types']);
        }

        if (($filters['is_complete_only'] ?? true)) {
            $recipesQuery->where('is_complete', 1);
        }

        $dailyPlanRecipes = $recipesQuery->get();

        if ($dailyPlanRecipes->isEmpty()) {
            abort(response()->json([
                'status' => true,
                'message' => __('message.not_found_entry', ['name' => __('message.daily_plan_recipe')]),
                'all_messages' => [
                    'daily_plan_id' => __('message.not_found_entry', ['name' => __('message.daily_plan_recipe')]),
                ]
            ], 422));
        }

        $dailyPlans = DailyPlan::whereIn('id', $dailyPlanIds)->get(['id', 'date']);
        $startDate = $dailyPlans->min('date') ?: now()->toDateString();
        $endDate = $dailyPlans->max('date') ?: now()->toDateString();

        $singleDailyPlanId = count($dailyPlanIds) === 1 ? $dailyPlanIds[0] : null;
        $defaultTitle = $singleDailyPlanId
            ? __('message.daily_plan') . ' ' . $startDate . ' ' . __('message.shopping_list')
            : __('message.daily_plan') . ' ' . $startDate . ' - ' . $endDate . ' ' . __('message.shopping_list');

        $consolidated = $this->consolidate($dailyPlanRecipes, $servings);

        return DB::transaction(function () use ($user, $filters, $singleDailyPlanId, $startDate, $endDate, $defaultTitle, $consolidated, $refreshList, $servings) {
            $shoppingList = $refreshList;
            $checkedByIngredient = collect();

            if ($shoppingList) {
                $checkedByIngredient = $shoppingList->items()
                    ->where('manually_added', 0)
                    ->where('is_checked', 1)
                    ->pluck('is_checked', 'ingredient_id');

                $shoppingList->update([
                    'daily_plan_id' => $singleDailyPlanId,
                    'title'         => $filters['title'] ?? $shoppingList->title ?? $defaultTitle,
                    'start_date'    => $startDate,
                    'end_date'      => $endDate,
                    'servings'      => $servings,
                    'status'        => 'active',
                ]);

                // Refresh only auto-generated items, keep manual entries.
                $shoppingList->items()->where('manually_added', 0)->delete();
            } else {
                $shoppingList = ShoppingList::create([
                    'user_id'       => $user->id,
                    'daily_plan_id' => $singleDailyPlanId,
                    'title'         => $filters['title'] ?? $defaultTitle,
                    'start_date'    => $startDate,
                    'end_date'      => $endDate,
                    'servings'      => $servings,
                    'status'        => 'active',
                ]);
            }

            if (!empty($consolidated)) {
                foreach ($consolidated as &$item) {
                    $item['is_checked'] = (int) ($checkedByIngredient[$item['ingredient_id']] ?? 0);
                    $item['shopping_list_id'] = $shoppingList->id;
                    $item['created_at'] = now();
                    $item['updated_at'] = now();
                }
                unset($item);

                ShoppingListItem::insert(array_values($consolidated));
            }

            return ShoppingList::withCount('items')->findOrFail($shoppingList->id);
        });
    }

    protected function resolveDailyPlanIds(User $user, array $filters): array
    {
        if (!empty($filters['daily_plan_id'])) {
            $dailyPlan = DailyPlan::where('id', $filters['daily_plan_id'])
                ->where('user_id', $user->id)
                ->first();

            if (!$dailyPlan) {
                abort(response()->json([
                    'status' => true,
                    'message' => __('message.not_found_entry', ['name' => __('message.daily_plan')]),
                    'all_messages' => [
                        'daily_plan_id' => __('message.not_found_entry', ['name' => __('message.daily_plan')]),
                    ]
                ], 422));
            }

            return [$dailyPlan->id];
        }

        $start_date = $filters['start_date'] ?? null;
        $end_date = $filters['end_date'] ?? null;

        if (!$start_date || !$end_date) {
            abort(response()->json([
                'status' => true,
                'message' => __('validation.required', ['attribute' => 'date range']),
                'all_messages' => [
                    'date_range' => __('validation.required', ['attribute' => 'date range']),
                ]
            ], 422));
        }

        $dailyPlanIds = DailyPlan::where('user_id', $user->id)
            ->whereBetween('date', [$start_date, $end_date])
            ->pluck('id')
            ->toArray();

        if (empty($dailyPlanIds)) {
            abort(response()->json([
                'status' => true,
                'message' => __('message.not_found_entry', ['name' => __('message.daily_plan')]),
                'all_messages' => [
                    'daily_plan_id' => __('message.not_found_entry', ['name' => __('message.daily_plan')]),
                ]
            ], 422));
        }

        return $dailyPlanIds;
    }

    protected function consolidate($dailyPlanRecipes, float $servings = 1): array
    {
        $consolidated = [];
        $servings = $servings > 0 ? $servings : 1;

        $gramUnitId = MeasurementUnit::where('unit_type', 'weight')
            ->where(function ($q) {
                $q->where('symbol', 'g')->orWhere('title', 'Gram');
            })
            ->value('id');

        foreach ($dailyPlanRecipes as $dailyPlanRecipe) {
            $recipe = $dailyPlanRecipe->recipe;
            if (!$recipe) {
                continue;
            }

            foreach ($recipe->recipeIngredients as $ri) {
                $ingredientId = $ri->ingredient_id;
                if (!$ingredientId) {
                    continue;
                }

                if (!isset($consolidated[$ingredientId])) {
                    $consolidated[$ingredientId] = [
                        'ingredient_id'     => $ingredientId,
                        'total_grams'       => 0,
                        'display_quantity'  => 0,
                        'measurement_unit_id' => $ri->measurement_unit_id,
                        'is_checked'        => 0,
                        'manually_added'    => 0,
                    ];
                }

                $lineGrams = $this->resolveIngredientGrams($ri);

                $consolidated[$ingredientId]['total_grams'] += ($lineGrams * $servings);
            }
        }

        foreach ($consolidated as $ingredientId => &$item) {
            $item['total_grams'] = round((float) $item['total_grams'], 2);
            [$displayQuantity, $measurementUnitId] = $this->deriveDisplayQuantity(
                $ingredientId,
                $item['measurement_unit_id'],
                $item['total_grams'],
                $gramUnitId
            );
            $item['display_quantity'] = $displayQuantity;
            $item['measurement_unit_id'] = $measurementUnitId;

            if ($item['display_quantity'] <= 0) {
                $item['display_quantity'] = $item['total_grams'];
                $item['measurement_unit_id'] = $gramUnitId;
            }
        }
        unset($item);

        return $consolidated;
    }

    protected function deriveDisplayQuantity(int $ingredientId, ?int $measurementUnitId, float $totalGrams, ?int $gramUnitId): array
    {
        if (!$measurementUnitId || $totalGrams <= 0) {
            return [round($totalGrams, 2), $measurementUnitId];
        }

        $unit = MeasurementUnit::find($measurementUnitId);
        if (!$unit) {
            return [round($totalGrams, 2), $gramUnitId];
        }

        if ($unit->unit_type === 'count') {
            $conversion = IngredientUnitConversion::where('ingredient_id', $ingredientId)
                ->where('measurement_unit_id', $measurementUnitId)
                ->value('gram_equivalent');

            if ($conversion && (float) $conversion > 0) {
                return [round($totalGrams / (float) $conversion, 2), $measurementUnitId];
            }

            return [round($totalGrams, 2), $gramUnitId];
        }

        if ($unit->unit_type === 'weight') {
            $factor = (float) ($unit->base_conversion_factor ?? 0);
            if ($factor > 0) {
                return [round($totalGrams / $factor, 2), $measurementUnitId];
            }

            return [round($totalGrams, 2), $gramUnitId];
        }

        $conversion = IngredientUnitConversion::where('ingredient_id', $ingredientId)
            ->where('measurement_unit_id', $measurementUnitId)
            ->value('gram_equivalent');

        if ($conversion && (float) $conversion > 0) {
            return [round($totalGrams / (float) $conversion, 2), $measurementUnitId];
        }

        return [round($totalGrams, 2), $gramUnitId];
    }

    protected function resolveIngredientGrams($recipeIngredient): float
    {
        $storedGrams = (float) ($recipeIngredient->quantity_grams ?? 0);
        if ($storedGrams > 0) {
            return $storedGrams;
        }

        $quantity = (float) ($recipeIngredient->quantity ?? 0);
        if ($quantity <= 0) {
            return 0.0;
        }

        $unit = null;
        if (!empty($recipeIngredient->measurement_unit_id)) {
            $unit = MeasurementUnit::find($recipeIngredient->measurement_unit_id);
        }

        if (!$unit) {
            $amount = (float) ($recipeIngredient->amount ?? 0);
            return $amount > 0 ? ($quantity * $amount) : 0.0;
        }

        $ingredientId = $recipeIngredient->ingredient_id;
        $conversion = null;
        if (!empty($ingredientId)) {
            $conversion = IngredientUnitConversion::where('ingredient_id', $ingredientId)
                ->where('measurement_unit_id', $unit->id)
                ->value('gram_equivalent');
        }

        if ($conversion && (float) $conversion > 0) {
            return $quantity * (float) $conversion;
        }

        $base = (float) ($unit->base_conversion_factor ?? 0);
        if ($base <= 0) {
            $amount = (float) ($recipeIngredient->amount ?? 0);
            return $amount > 0 ? ($quantity * $amount) : 0.0;
        }

        if ($unit->unit_type === 'volume') {
            $density = (float) ($recipeIngredient?->ingredient?->density ?? 1.0);
            if ($density <= 0) {
                $density = 1.0;
            }
            return $quantity * $base * $density;
        }

        return $quantity * $base;
    }
}
