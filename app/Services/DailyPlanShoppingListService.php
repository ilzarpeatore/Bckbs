<?php

namespace App\Services;

use App\Models\DailyPlan;
use App\Models\DailyPlanRecipe;
use App\Models\FatSecretRecipeCache;
use App\Models\IngredientUnitConversion;
use App\Models\MeasurementUnit;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        // Comidas asignadas desde FatSecret (sin ingredient_id local): líneas de texto.
        $fatsecretLines = $this->consolidateFatSecret($dailyPlanRecipes, $servings);

        return DB::transaction(function () use ($user, $filters, $singleDailyPlanId, $startDate, $endDate, $defaultTitle, $consolidated, $fatsecretLines, $refreshList, $servings) {
            $shoppingList = $refreshList;
            $checkedKeys = collect();

            if ($shoppingList) {
                // Lo ya marcado como comprado se conserva al regenerar: por ingrediente
                // para los locales y por nombre+unidad para las líneas de texto.
                $checkedKeys = $shoppingList->items()
                    ->where('manually_added', 0)
                    ->where('is_checked', 1)
                    ->get()
                    ->mapWithKeys(fn ($i) => [$this->itemKey($i->ingredient_id, $i->custom_item_name, $i->unit_label) => true]);

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

            // Todas las filas con las mismas columnas: insert() en bloque las exige.
            $rows = [];
            foreach (array_merge(array_values($consolidated), array_values($fatsecretLines)) as $item) {
                $key = $this->itemKey($item['ingredient_id'] ?? null, $item['custom_item_name'] ?? null, $item['unit_label'] ?? null);
                $rows[] = [
                    'shopping_list_id'    => $shoppingList->id,
                    'ingredient_id'       => $item['ingredient_id'] ?? null,
                    'custom_item_name'    => $item['custom_item_name'] ?? null,
                    'total_grams'         => $item['total_grams'] ?? null,
                    'display_quantity'    => $item['display_quantity'] ?? null,
                    'measurement_unit_id' => $item['measurement_unit_id'] ?? null,
                    'unit_label'          => $item['unit_label'] ?? null,
                    'is_checked'          => (int) ($checkedKeys[$key] ?? 0),
                    'manually_added'      => 0,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }
            if (!empty($rows)) {
                ShoppingListItem::insert($rows);
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

    /** Clave estable de una línea para conservar "comprado" al regenerar la lista. */
    protected function itemKey($ingredientId, ?string $name, ?string $unit): string
    {
        if ($ingredientId) {
            return 'i:' . $ingredientId;
        }

        return 't:' . $this->normalizeText((string) $name) . '|' . $this->normalizeUnit((string) $unit);
    }

    protected function normalizeText(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::ascii(mb_strtolower($s))));
    }

    /** "tbsps" y "tbsp" (o "tazas" y "taza") son la misma unidad. */
    protected function normalizeUnit(string $unit): string
    {
        $u = $this->normalizeText($unit);

        return strlen($u) > 3 ? rtrim($u, 's') : $u;
    }

    /**
     * Ingredientes de las comidas de FatSecret -> líneas de texto consolidadas.
     * La receta trae cantidades para TODAS sus raciones (number_of_servings) y
     * la comida del plan es una ración, así que se divide entre las raciones de
     * la receta y se multiplica por las raciones de la lista. Las que no están
     * en la caché de recetas (nunca se abrieron) se omiten y se dejan en el log.
     */
    protected function consolidateFatSecret($dailyPlanRecipes, float $servings = 1): array
    {
        $meals = $dailyPlanRecipes->filter(fn ($m) => !empty($m->fatsecret_recipe_id));
        if ($meals->isEmpty()) {
            return [];
        }

        $caches = FatSecretRecipeCache::whereIn('fatsecret_recipe_id', $meals->pluck('fatsecret_recipe_id')->unique()->all())
            ->get()
            ->keyBy('fatsecret_recipe_id');

        $lines = [];
        foreach ($meals as $meal) {
            $cache = $caches->get($meal->fatsecret_recipe_id);
            if (!$cache || !is_array($cache->ingredients)) {
                Log::warning('[shopping-list] receta FatSecret sin detalle en caché, se omite', [
                    'fatsecret_recipe_id' => $meal->fatsecret_recipe_id,
                    'daily_plan_recipe_id' => $meal->id,
                ]);
                continue;
            }

            $recipeServings = max(1.0, (float) ($cache->number_of_servings ?: 1));
            foreach ($cache->ingredients as $ingredient) {
                $description = trim((string) ($ingredient['description'] ?? ''));
                if ($description === '') {
                    continue;
                }

                [$name, $unit, $quantity] = $this->parseFatSecretLine($description, $ingredient);
                if ($name === '') {
                    continue;
                }

                $key = $this->normalizeText($name) . '|' . $this->normalizeUnit($unit);
                $lines[$key] ??= ['name' => $name, 'unit' => $unit, 'quantity' => 0.0];
                $lines[$key]['quantity'] += ($quantity / $recipeServings) * $servings;
            }
        }

        return array_map(fn ($l) => [
            'ingredient_id'       => null,
            'custom_item_name'    => $l['name'],
            'total_grams'         => null,
            'display_quantity'    => $l['quantity'] > 0 ? round($l['quantity'], 2) : null,
            'measurement_unit_id' => null,
            'unit_label'          => $l['unit'] !== '' ? $l['unit'] : null,
        ], array_values($lines));
    }

    /**
     * "1 1/4 tsps garlic, minced" -> ['Garlic', 'tsps', 1.25]. Quita la cantidad inicial, la
     * unidad (si la hay, en español o inglés) y lo que sigue a la primera coma (preparación).
     * La cantidad sale de number_of_units si viene y, si no, de lo escrito en la descripción.
     */
    protected function parseFatSecretLine(string $description, array $ingredient): array
    {
        $rest = $description;
        $written = 0.0;
        if (preg_match('/^\s*(\d+\s+\d+\/\d+|\d+\/\d+|\d+(?:[.,]\d+)?)(?:\s*[-–]\s*(?:\d+\s+\d+\/\d+|\d+\/\d+|\d+(?:[.,]\d+)?))?\s*/u', $rest, $m)) {
            $written = $this->parseQuantity($m[1]);
            $rest = substr($rest, strlen($m[0]));
        }

        $unit = '';
        $units = 'cucharadas?|cucharaditas?|tazas?|tbsps?|tsps?|cups?|oz|onzas?|libras?|lbs?|g|gr|gramos?|kg|kilos?|ml|l|litros?|dientes?|rebanadas?|lonchas?|latas?|piezas?|unidades?|pizcas?|pinch(?:es)?|slices?|cloves?|cans?|packages?|sprigs?';
        if (preg_match('/^(' . $units . ')\b\.?\s*(?:de |of )?/iu', $rest, $m)) {
            $unit = mb_strtolower($m[1]);
            $rest = substr($rest, strlen($m[0]));
        }

        $name = trim(explode(',', $rest)[0]);
        $name = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $name));
        $name = $name === '' ? '' : mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);

        $units = (float) ($ingredient['number_of_units'] ?? 0);
        $quantity = $units > 0 ? $units : $written;

        return [$name, $unit, $quantity];
    }

    protected function parseQuantity(string $raw): float
    {
        $raw = str_replace(',', '.', trim($raw));
        if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $raw, $m)) {
            return (float) $m[1] + ((float) $m[2] / max(1, (float) $m[3]));
        }
        if (preg_match('/^(\d+)\/(\d+)$/', $raw, $m)) {
            return (float) $m[1] / max(1, (float) $m[2]);
        }

        return (float) $raw;
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
