<?php

namespace App\Http\Controllers\API\Admin;

use App\Exceptions\FatSecretUnavailableException;
use App\Http\Controllers\Concerns\BuildsFatSecretRecipeSearchFilters;
use App\Http\Controllers\Controller;
use App\Services\FatSecret\FatSecretFoodService;
use App\Services\FatSecret\FatSecretRecipeService;
use Illuminate\Http\Request;

/**
 * Panel admin: (1) búsqueda de alimentos genéricos para autocompletar
 * Ingredient::calories_per_gram/etc, (2) búsqueda/detalle de recetas de
 * FatSecret para que el coach asigne una a un cliente. Ver
 * docs/FATSECRET_INTEGRATION.md secciones 4 y 9. Nunca falla el flujo
 * normal del admin si FatSecret no responde -- son ayudas opcionales.
 */
class FatSecretController extends Controller
{
    use BuildsFatSecretRecipeSearchFilters;

    public function __construct(
        private readonly FatSecretFoodService $foodService,
        private readonly FatSecretRecipeService $recipeService,
    ) {
    }

    public function searchFoods(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);

        try {
            $results = $this->foodService->search($request->q);
        } catch (FatSecretUnavailableException $e) {
            return json_message_response($e->getMessage(), 503);
        }

        return json_custom_response(['data' => $results]);
    }

    public function showFood(int $foodId)
    {
        try {
            $result = $this->foodService->detail($foodId);
        } catch (FatSecretUnavailableException $e) {
            return json_message_response($e->getMessage(), 503);
        }

        return json_custom_response(['data' => $result]);
    }

    public function searchRecipes(Request $request)
    {
        $request->validate($this->recipeSearchValidationRules());

        try {
            $results = $this->recipeService->search(
                $request->q,
                'US',
                (int) $request->get('page', 0),
                $this->recipeSearchFilters($request)
            );
        } catch (FatSecretUnavailableException $e) {
            return json_message_response($e->getMessage(), 503);
        }

        return json_custom_response(['data' => $results]);
    }

    public function showRecipe(int $recipeId)
    {
        try {
            $recipe = $this->recipeService->getOrRefresh($recipeId);
        } catch (FatSecretUnavailableException $e) {
            return json_message_response($e->getMessage(), 503);
        }

        return json_custom_response(['data' => $recipe]);
    }
}
