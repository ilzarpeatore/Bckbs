<?php

namespace App\Services\FatSecret;

use App\Models\FatSecretRecipeCache;

/**
 * Recetas de FatSecret servidas SIEMPRE en vivo/cache-aside de corta
 * duración -- nunca importadas de forma permanente, ver
 * docs/FATSECRET_INTEGRATION.md secciones 0 y 9. Usado tanto por el panel
 * admin (coach asigna una receta a un cliente) como por la app del cliente
 * (buscar una comida para sustituir la asignada).
 *
 * VERIFICAR ANTES DE PRODUCCIÓN (ver docs/FATSECRET_INTEGRATION.md sección
 * 7): los nombres de método exactos de recipe.get (¿v1/v2/v3?) no están
 * confirmados con una llamada real todavía -- v2 es la versión cuyo schema
 * se documentó al diseñar esto, pero está marcada deprecated en su portal.
 * Probar contra la cuenta Basic real y ajustar RECIPE_GET_METHOD si hace
 * falta antes de dar esto por cerrado.
 */
class FatSecretRecipeService
{
    private const RECIPE_SEARCH_METHOD = 'recipes.search.v3';
    private const RECIPE_GET_METHOD = 'recipe.get.v2';

    public function __construct(private readonly FatSecretClient $client)
    {
    }

    /**
     * Resultados de búsqueda con foto+macros ya embebidos -- 1 sola llamada
     * a la API renderiza toda la parrilla, nunca 1 llamada por receta
     * mostrada (confirmado en la documentación de recipes.search, ver
     * docs/FATSECRET_INTEGRATION.md sección 9).
     *
     * @return array{results: array<int, array>, total_results: int, page_number: int}
     */
    public function search(string $query, string $region = 'US', int $page = 0): array
    {
        $data = $this->client->call(self::RECIPE_SEARCH_METHOD, [
            'search_expression' => $query,
            'region' => $region,
            'page_number' => $page,
            'max_results' => 50,
        ]);

        $container = $data['recipes'] ?? [];
        $recipes = $this->normalizeList($container['recipe'] ?? []);

        $results = array_map(function (array $r) {
            $nutrition = $r['recipe_nutrition'] ?? [];

            return [
                'fatsecret_recipe_id' => (int) $r['recipe_id'],
                'name' => $r['recipe_name'],
                'description' => $r['recipe_description'] ?? null,
                'image_url' => $r['recipe_image'] ?? null,
                'calories' => (float) ($nutrition['calories'] ?? 0),
                'protein' => (float) ($nutrition['protein'] ?? 0),
                'fat' => (float) ($nutrition['fat'] ?? 0),
                'carbs' => (float) ($nutrition['carbohydrate'] ?? 0),
            ];
        }, $recipes);

        return [
            'results' => $results,
            'total_results' => (int) ($container['total_results'] ?? count($results)),
            'page_number' => (int) ($container['page_number'] ?? $page),
        ];
    }

    /**
     * Cache-aside: si hay una copia de menos de FatSecretRecipeCache::TTL_HOURS
     * se sirve directa (0 llamadas a FatSecret); si no, se pide en vivo y se
     * refresca la copia. Esto es lo único que debe usarse para mostrar el
     * detalle completo (pasos, ingredientes) de una receta de FatSecret --
     * nunca guardar este contenido en ningún sitio con TTL "para siempre".
     */
    public function getOrRefresh(int $recipeId, string $region = 'US'): FatSecretRecipeCache
    {
        $cached = FatSecretRecipeCache::find($recipeId);
        if ($cached && $cached->isFresh()) {
            return $cached;
        }

        $data = $this->client->call(self::RECIPE_GET_METHOD, [
            'recipe_id' => $recipeId,
            'region' => $region,
        ]);

        $recipe = $data['recipe'] ?? [];

        $directions = array_map(
            fn (array $d) => $d['direction_description'],
            $this->normalizeList($recipe['directions']['direction'] ?? [])
        );

        $ingredients = array_map(fn (array $i) => [
            'description' => $i['ingredient_description'] ?? null,
            'food_id' => isset($i['food_id']) ? (int) $i['food_id'] : null,
            'number_of_units' => isset($i['number_of_units']) ? (float) $i['number_of_units'] : null,
            'measurement_description' => $i['measurement_description'] ?? null,
        ], $this->normalizeList($recipe['ingredients']['ingredient'] ?? []));

        $servingSizes = $this->normalizeList($recipe['serving_sizes']['serving'] ?? []);
        $mainServing = $servingSizes[0] ?? [];

        $images = $this->normalizeList($recipe['recipe_images']['recipe_image'] ?? []);

        return FatSecretRecipeCache::updateOrCreate(
            ['fatsecret_recipe_id' => $recipeId],
            [
                'name' => $recipe['recipe_name'] ?? '',
                'image_url' => $images[0] ?? null,
                'calories' => (float) ($mainServing['calories'] ?? 0),
                'protein' => (float) ($mainServing['protein'] ?? 0),
                'fat' => (float) ($mainServing['fat'] ?? 0),
                'carbs' => (float) ($mainServing['carbohydrate'] ?? 0),
                'number_of_servings' => (float) ($recipe['number_of_servings'] ?? 1),
                'preparation_time_min' => isset($recipe['preparation_time_min']) ? (int) $recipe['preparation_time_min'] : null,
                'cooking_time_min' => isset($recipe['cooking_time_min']) ? (int) $recipe['cooking_time_min'] : null,
                'directions' => $directions,
                'ingredients' => $ingredients,
                'fetched_at' => now(),
            ]
        );
    }

    private function normalizeList(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }
        if (array_is_list($value)) {
            return $value;
        }
        return [$value];
    }
}
