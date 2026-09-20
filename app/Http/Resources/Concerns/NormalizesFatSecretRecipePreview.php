<?php

namespace App\Http\Resources\Concerns;

use App\Models\FatSecretRecipeCache;

/**
 * Compartido entre DailyPlanRecipeResource y MealPlanTemplateItemResource --
 * mismo shape de 'recipe' para ambos orígenes (propia o FatSecret), lectura
 * PASIVA del cache-aside (nunca fuerza un refresco aquí, eso solo lo hace
 * FatSecretController::show() cuando el usuario abre el detalle). Ver
 * docs/FATSECRET_INTEGRATION.md sección 9.
 */
trait NormalizesFatSecretRecipePreview
{
    protected function resolveFatSecretRecipePreview(?int $fatsecretRecipeId, float $calories, float $protein, float $fats, float $carbs): ?array
    {
        if (!$fatsecretRecipeId) {
            return null;
        }

        $cached = FatSecretRecipeCache::find($fatsecretRecipeId);

        return [
            'id'           => $fatsecretRecipeId,
            'source'       => 'fatsecret',
            'title'        => $cached->name ?? null,
            'recipe_image' => $cached->image_url ?? null,
            'calories'     => round($calories),
            'protein'      => round($protein),
            'fats'         => round($fats),
            'carbs'        => round($carbs),
        ];
    }
}
