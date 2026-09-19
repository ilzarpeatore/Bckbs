<?php

namespace App\Http\Controllers\API;

use App\Exceptions\FatSecretUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\FatSecret\FatSecretRecipeService;
use Illuminate\Http\Request;

/**
 * Búsqueda de recetas de FatSecret desde la app del cliente, para sustituir
 * una comida ya asignada (pedido explícito 2026-09-19, ver
 * docs/FATSECRET_INTEGRATION.md sección 9). Mismo mecanismo que el panel
 * admin (App\Http\Controllers\API\Admin\FatSecretController) pero con auth
 * de cliente y throttle propio -- ver routes/api.php (throttle:100,1440,
 * red de seguridad por si el buscador se usa más de lo previsto y se acerca
 * al límite diario de la cuenta Basic).
 */
class FatSecretController extends Controller
{
    public function __construct(private readonly FatSecretRecipeService $recipeService)
    {
    }

    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'page' => 'nullable|integer|min:0',
        ]);

        try {
            $results = $this->recipeService->search($request->q, 'US', (int) $request->get('page', 0));
        } catch (FatSecretUnavailableException $e) {
            return json_message_response($e->getMessage(), 503);
        }

        return json_custom_response(['data' => $results]);
    }

    public function show(int $recipeId)
    {
        try {
            $recipe = $this->recipeService->getOrRefresh($recipeId);
        } catch (FatSecretUnavailableException $e) {
            return json_message_response($e->getMessage(), 503);
        }

        return json_custom_response(['data' => $recipe]);
    }
}
