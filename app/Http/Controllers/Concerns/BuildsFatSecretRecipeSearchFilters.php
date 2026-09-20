<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Filtros server-side de `recipes.search.v3` -- todos disponibles en plan
 * Basic (verificado contra la documentación real 2026-09-20, ver
 * docs/FATSECRET_INTEGRATION.md sección 13). Compartido entre
 * `API\Admin\FatSecretController` (coach) y `API\FatSecretController`
 * (cliente) -- mismas reglas y mismo mapeo hacia
 * `FatSecretRecipeService::search()`. Todos opcionales: omitirlos se
 * comporta exactamente igual que antes de que existiera este trait.
 */
trait BuildsFatSecretRecipeSearchFilters
{
    private function recipeSearchValidationRules(): array
    {
        return [
            'q' => 'required|string|min:2',
            'page' => 'nullable|integer|min:0',
            'calories_from' => 'nullable|integer|min:0',
            'calories_to' => 'nullable|integer|min:0',
            'protein_percentage_from' => 'nullable|integer|min:0|max:100',
            'protein_percentage_to' => 'nullable|integer|min:0|max:100',
            'carb_percentage_from' => 'nullable|integer|min:0|max:100',
            'carb_percentage_to' => 'nullable|integer|min:0|max:100',
            'fat_percentage_from' => 'nullable|integer|min:0|max:100',
            'fat_percentage_to' => 'nullable|integer|min:0|max:100',
            'prep_time_from' => 'nullable|integer|min:0',
            'prep_time_to' => 'nullable|integer|min:0',
            'recipe_types' => 'nullable|array',
            'recipe_types.*' => 'string',
            'recipe_types_matchall' => 'nullable|boolean',
            'must_have_images' => 'nullable|boolean',
            'sort_by' => 'nullable|string|in:newest,oldest,caloriesPerServingAscending,caloriesPerServingDescending',
        ];
    }

    private function recipeSearchFilters(Request $request): array
    {
        return array_filter([
            'caloriesFrom' => $request->get('calories_from'),
            'caloriesTo' => $request->get('calories_to'),
            'proteinPercentageFrom' => $request->get('protein_percentage_from'),
            'proteinPercentageTo' => $request->get('protein_percentage_to'),
            'carbPercentageFrom' => $request->get('carb_percentage_from'),
            'carbPercentageTo' => $request->get('carb_percentage_to'),
            'fatPercentageFrom' => $request->get('fat_percentage_from'),
            'fatPercentageTo' => $request->get('fat_percentage_to'),
            'prepTimeFrom' => $request->get('prep_time_from'),
            'prepTimeTo' => $request->get('prep_time_to'),
            'recipeTypes' => $request->get('recipe_types'),
            'recipeTypesMatchAll' => $request->get('recipe_types_matchall'),
            'mustHaveImages' => $request->get('must_have_images'),
            'sortBy' => $request->get('sort_by'),
        ], fn ($v) => $v !== null);
    }
}
