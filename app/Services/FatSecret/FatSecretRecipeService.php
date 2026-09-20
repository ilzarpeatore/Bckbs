<?php

namespace App\Services\FatSecret;

use App\Models\FatSecretRecipeCache;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeCategoryMapping;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Services\Translation\DeepLTranslationService;

/**
 * Recetas de FatSecret servidas SIEMPRE en vivo/cache-aside de corta
 * duración -- nunca importadas de forma permanente, ver
 * docs/FATSECRET_INTEGRATION.md secciones 0 y 9. Usado tanto por el panel
 * admin (coach asigna una receta a un cliente) como por la app del cliente
 * (buscar una comida para sustituir la asignada).
 *
 * CONFIRMADO 2026-09-20 contra la documentación real (ver
 * docs/FATSECRET_INTEGRATION.md sección 13): `recipe.get.v2` SÍ es la
 * versión vigente/recomendada, no está deprecada -- no hace falta migrar a
 * ninguna v3. `recipes.search.v3` es igualmente la versión actual.
 */
class FatSecretRecipeService
{
    private const RECIPE_SEARCH_METHOD = 'recipes.search.v3';
    private const RECIPE_GET_METHOD = 'recipe.get.v2';

    public function __construct(
        private readonly FatSecretClient $client,
        private readonly DeepLTranslationService $translator,
    ) {
    }

    /**
     * Resultados de búsqueda con foto+macros ya embebidos -- 1 sola llamada
     * a la API renderiza toda la parrilla, nunca 1 llamada por receta
     * mostrada (confirmado en la documentación de recipes.search, ver
     * docs/FATSECRET_INTEGRATION.md sección 9).
     *
     * $filters (todos opcionales, disponibles en plan Basic -- verificado
     * contra la documentación real 2026-09-20, ver
     * docs/FATSECRET_INTEGRATION.md sección 13): caloriesFrom/caloriesTo,
     * proteinPercentageFrom/To, carbPercentageFrom/To, fatPercentageFrom/To,
     * prepTimeFrom/To (minutos), recipeTypes (array<string>, ej. ["Main
     * Dish"]), recipeTypesMatchAll (bool), mustHaveImages (bool), sortBy (uno
     * de: newest, oldest, caloriesPerServingAscending,
     * caloriesPerServingDescending).
     *
     * @param  array<string, mixed>  $filters
     * @return array{results: array<int, array>, total_results: int, page_number: int}
     */
    public function search(string $query, string $region = 'US', int $page = 0, array $filters = []): array
    {
        $data = $this->client->call(self::RECIPE_SEARCH_METHOD, array_merge([
            'search_expression' => $query,
            'region' => $region,
            'page_number' => $page,
            'max_results' => 50,
        ], $this->buildSearchFilterParams($filters)));

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

        $nameEn = $recipe['recipe_name'] ?? '';

        $directionsEn = array_map(
            fn (array $d) => $d['direction_description'],
            $this->normalizeList($recipe['directions']['direction'] ?? [])
        );

        $ingredientsEn = array_map(fn (array $i) => [
            'description' => $i['ingredient_description'] ?? null,
            'food_id' => isset($i['food_id']) ? (int) $i['food_id'] : null,
            'number_of_units' => isset($i['number_of_units']) ? (float) $i['number_of_units'] : null,
            'measurement_description' => $i['measurement_description'] ?? null,
        ], $this->normalizeList($recipe['ingredients']['ingredient'] ?? []));

        [$name, $directions, $ingredients, $isTranslated] = $this->translateRecipeContent($nameEn, $directionsEn, $ingredientsEn);

        $servingSizes = $this->normalizeList($recipe['serving_sizes']['serving'] ?? []);
        $mainServing = $servingSizes[0] ?? [];

        $images = $this->normalizeList($recipe['recipe_images']['recipe_image'] ?? []);

        return FatSecretRecipeCache::updateOrCreate(
            ['fatsecret_recipe_id' => $recipeId],
            [
                'name' => $name,
                'name_en' => $nameEn,
                'image_url' => $images[0] ?? null,
                'calories' => (float) ($mainServing['calories'] ?? 0),
                'protein' => (float) ($mainServing['protein'] ?? 0),
                'fat' => (float) ($mainServing['fat'] ?? 0),
                'carbs' => (float) ($mainServing['carbohydrate'] ?? 0),
                'number_of_servings' => (float) ($recipe['number_of_servings'] ?? 1),
                'preparation_time_min' => isset($recipe['preparation_time_min']) ? (int) $recipe['preparation_time_min'] : null,
                'cooking_time_min' => isset($recipe['cooking_time_min']) ? (int) $recipe['cooking_time_min'] : null,
                'directions' => $directions,
                'directions_en' => $directionsEn,
                'ingredients' => $ingredients,
                'ingredients_en' => $ingredientsEn,
                'is_translated' => $isTranslated,
                'fetched_at' => now(),
            ]
        );
    }

    /**
     * Traduce nombre + pasos + descripciones de ingrediente en UNA sola
     * llamada a DeepL (permiso explícito de FatSecret, 2026-09-20 -- ver
     * docs/FATSECRET_INTEGRATION.md sección 10). Deliberadamente NO se
     * traduce nada en search() -- solo el detalle que entra en el cache,
     * decisión de coste/latencia (search puede devolver hasta 50 resultados
     * por llamada).
     *
     * @param  array<int, array{description: ?string, food_id: ?int, number_of_units: ?float, measurement_description: ?string}>  $ingredientsEn
     * @return array{0: string, 1: array<int, string>, 2: array<int, array>, 3: bool}
     */
    private function translateRecipeContent(string $nameEn, array $directionsEn, array $ingredientsEn): array
    {
        // Las descripciones de ingrediente pueden venir null -- se excluyen
        // del lote a traducir y se reinsertan tal cual en su posición
        // original, en vez de mandarle un string vacío a DeepL.
        $ingredientDescriptions = array_map(fn (array $i) => $i['description'], $ingredientsEn);
        $translatableIndexes = array_keys(array_filter($ingredientDescriptions, fn ($d) => $d !== null && $d !== ''));

        $batch = array_merge(
            [$nameEn],
            $directionsEn,
            array_map(fn ($i) => $ingredientDescriptions[$i], $translatableIndexes)
        );

        $translated = $this->translator->translateMany($batch);

        $name = $translated[0] ?? $nameEn;
        $directions = array_slice($translated, 1, count($directionsEn));

        $ingredients = $ingredientsEn;
        $translatedDescriptionsOffset = 1 + count($directionsEn);
        foreach ($translatableIndexes as $position => $ingredientIndex) {
            $ingredients[$ingredientIndex]['description'] = $translated[$translatedDescriptionsOffset + $position] ?? $ingredientDescriptions[$ingredientIndex];
        }

        return [$name, $directions, $ingredients, (bool) config('services.deepl.api_key')];
    }

    /**
     * Traduce el array de filtros de negocio a los nombres literales exactos
     * que exige `recipes.search.v3` (confirmados contra la documentación
     * real, no adivinados -- el `.` de "calories.from" es parte del nombre
     * del parámetro, no notación de array anidado). Solo se añaden las
     * claves presentes -- omitir un filtro debe comportarse exactamente
     * igual que antes de que existiera esta función.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildSearchFilterParams(array $filters): array
    {
        $map = [
            'caloriesFrom' => 'calories.from',
            'caloriesTo' => 'calories.to',
            'proteinPercentageFrom' => 'protein_percentage.from',
            'proteinPercentageTo' => 'protein_percentage.to',
            'carbPercentageFrom' => 'carb_percentage.from',
            'carbPercentageTo' => 'carb_percentage.to',
            'fatPercentageFrom' => 'fat_percentage.from',
            'fatPercentageTo' => 'fat_percentage.to',
            'prepTimeFrom' => 'prep_time.from',
            'prepTimeTo' => 'prep_time.to',
        ];

        $params = [];
        foreach ($map as $key => $apiParam) {
            if (isset($filters[$key])) {
                $params[$apiParam] = $filters[$key];
            }
        }

        // BUG REAL confirmado en vivo (2026-09-20): FatSecret exige el
        // string literal "true"/"false" para sus parámetros booleanos --
        // "1"/"0" (lo que produce el cast `boolean` de Laravel/PHP) lo
        // ignora en silencio y no filtra nada, sin devolver error.
        foreach (['recipeTypesMatchAll' => 'recipe_types_matchall', 'mustHaveImages' => 'must_have_images'] as $key => $apiParam) {
            if (isset($filters[$key])) {
                $params[$apiParam] = filter_var($filters[$key], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            }
        }

        if (!empty($filters['recipeTypes'])) {
            $params['recipe_types'] = implode(',', (array) $filters['recipeTypes']);
        }

        if (!empty($filters['sortBy']) && in_array($filters['sortBy'], [
            'newest', 'oldest', 'caloriesPerServingAscending', 'caloriesPerServingDescending',
        ], true)) {
            $params['sort_by'] = $filters['sortBy'];
        }

        return $params;
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

    /**
     * DECISIÓN DEL USUARIO (2026-09-20): guardar copia permanente en la
     * biblioteca propia (`recipes`) de cualquier receta de FatSecret usada en
     * un plan, "por si quisiera modificar algo" -- pese a la reserva legal
     * documentada en docs/FATSECRET_INTEGRATION.md sección 0 (su ToS solo
     * permite guardar IDs indefinidamente, el resto del contenido debería
     * refrescarse cada 24h). Riesgo asumido explícitamente, ver sección 11.
     *
     * Idempotente por `recipes.fatsecret_recipe_id` (único) -- si ya se
     * importó antes, devuelve la Recipe existente sin volver a llamar a
     * FatSecret ni duplicar filas.
     *
     * Usa el contenido YA TRADUCIDO de `getOrRefresh()` (name/directions/
     * ingredients en español vía DeepL, ver sección 10) -- nunca el `_en`.
     *
     * Simplificación deliberada en `recipe_ingredients`: cada línea de
     * ingrediente de FatSecret es texto libre con cantidad ya incluida (ej.
     * "2 tazas de queso cheddar rallado"), no un ingrediente genérico
     * reutilizable con unidad separada -- no hay forma fiable de convertir
     * "2 tazas"/"1 loncha" a gramos exactos sin inventar una conversión (ver
     * la misma regla que ya aplica FatSecretFoodService::detail() para
     * ingredientes sueltos). Por eso cada `Ingredient` creado aquí representa
     * la línea completa tal cual, con `fatsecret_food_id` a null (no se
     * reutiliza entre recetas -- si se pusiera aquí, el mismo food_id en dos
     * recetas distintas violaría el índice único que ya usa el flujo manual
     * de ingredientes) y macros de línea en 0. Los macros de la RECETA (los
     * que de verdad importan para el plan) sí son exactos, copiados directos
     * del total de FatSecret. El coach puede editar cualquier ingrediente a
     * mano después si quiere precisión línea a línea.
     */
    public function importToLibrary(int $fatsecretRecipeId, ?string $mealType = null): Recipe
    {
        $existing = Recipe::where('fatsecret_recipe_id', $fatsecretRecipeId)->first();
        if ($existing) {
            return $existing;
        }

        $cache = $this->getOrRefresh($fatsecretRecipeId);

        $recipe = Recipe::create([
            'title' => $cache->name,
            'meal_type' => $mealType ? [$mealType] : null,
            'description' => $cache->name_en !== $cache->name ? "Original (FatSecret, inglés): {$cache->name_en}" : null,
            'preparation_time' => $cache->preparation_time_min ?? $cache->cooking_time_min,
            'calories' => $cache->calories,
            'protein' => $cache->protein,
            'fats' => $cache->fat,
            'carbs' => $cache->carbs,
            'status' => 'active',
            'fatsecret_recipe_id' => $fatsecretRecipeId,
        ]);

        foreach ($cache->directions ?? [] as $sequence => $instruction) {
            RecipeStep::create([
                'recipe_id' => $recipe->id,
                'instruction' => $instruction,
                'sequence' => $sequence,
            ]);
        }

        foreach ($cache->ingredients ?? [] as $ing) {
            $description = $ing['description'] ?? null;
            if (!$description) {
                continue;
            }
            $ingredient = Ingredient::create([
                'title' => $description,
                'status' => 'active',
                // fatsecret_food_id deliberadamente null aquí, ver docblock de este método.
            ]);
            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => $ing['number_of_units'] ?? 1,
            ]);
        }

        if ($mealType && ($categoryId = config('macro-nutrient.MEAL_TYPE_CATEGORY.' . $mealType))) {
            RecipeCategoryMapping::create([
                'recipe_id' => $recipe->id,
                'recipe_category_id' => $categoryId,
            ]);
        }

        return $recipe;
    }
}
