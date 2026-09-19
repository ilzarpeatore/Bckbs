<?php

namespace App\Console\Commands;

use App\Exceptions\FatSecretUnavailableException;
use App\Models\Ingredient;
use App\Services\FatSecret\FatSecretFoodService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Refresco periódico de los ingredientes vinculados a FatSecret -- cumple su
 * política de no servir contenido con más de 24h sin refrescar (ver
 * docs/FATSECRET_INTEGRATION.md secciones 0 y 6) de forma barata: un
 * alimento genérico casi nunca cambia de verdad, mensual sobra de margen.
 * Recalcula SIEMPRE con la misma fatsecret_serving_id ya guardada -- nunca
 * con la ración "default" del día, que además es un flag Premier-exclusivo
 * en el que no hay que confiar en Basic.
 */
class RefreshFatSecretIngredients extends Command
{
    protected $signature = 'fatsecret:refresh-ingredients';
    protected $description = 'Refresca la nutrición por gramo de los ingredientes vinculados a FatSecret';

    public function handle(FatSecretFoodService $foodService): int
    {
        $ingredients = Ingredient::whereNotNull('fatsecret_food_id')
            ->whereNotNull('fatsecret_serving_id')
            ->get();

        $this->info("Refrescando {$ingredients->count()} ingredientes vinculados a FatSecret...");

        foreach ($ingredients as $ingredient) {
            try {
                $fresh = $foodService->recalculateWithServing(
                    $ingredient->fatsecret_food_id,
                    $ingredient->fatsecret_serving_id,
                );
            } catch (FatSecretUnavailableException $e) {
                Log::warning("fatsecret:refresh-ingredients: fallo en {$ingredient->title} (id={$ingredient->id}): {$e->getMessage()}");
                continue;
            }

            if ($fresh === null) {
                Log::warning("fatsecret:refresh-ingredients: {$ingredient->title} (id={$ingredient->id}) -- la ración {$ingredient->fatsecret_serving_id} ya no existe en FatSecret, revisar a mano.");
                continue;
            }

            $before = $ingredient->calories_per_gram;
            $ingredient->update(array_merge($fresh, ['fatsecret_synced_at' => now()]));

            $diff = $before > 0 ? abs($fresh['calories_per_gram'] - $before) / $before : 0;
            if ($diff > 0.05) {
                Log::info("fatsecret:refresh-ingredients: {$ingredient->title} (id={$ingredient->id}) cambió más de un 5% en calorías/gramo ({$before} -> {$fresh['calories_per_gram']}), revisar.");
            }
        }

        $this->info('Listo.');

        return self::SUCCESS;
    }
}
