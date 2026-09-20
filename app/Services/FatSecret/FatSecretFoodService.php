<?php

namespace App\Services\FatSecret;

/**
 * Búsqueda de alimentos genéricos + cálculo de nutrición por gramo, ver
 * docs/FATSECRET_INTEGRATION.md secciones 4.1-4.2. Solo se usa para
 * autocompletar Ingredient::calories_per_gram/etc -- nunca para importar
 * recetas ni imágenes de alimento.
 */
class FatSecretFoodService
{
    public function __construct(private readonly FatSecretClient $client)
    {
    }

    /**
     * Lista simplificada para el autocompletado del admin -- food_id/nombre/
     * tipo únicamente, sin nutrición todavía (se pide en detail()).
     *
     * @return array<int, array{food_id:int, food_name:string, food_type:string, brand_name:?string}>
     */
    public function search(string $query, string $region = 'US'): array
    {
        $data = $this->client->call('foods.search', [
            'search_expression' => $query,
            'region' => $region,
            'max_results' => 50,
        ]);

        // FatSecret (XML->JSON) devuelve un objeto en vez de array de 1
        // elemento cuando solo hay un resultado -- normalizar siempre.
        $foods = $this->normalizeList($data['foods']['food'] ?? []);

        return array_map(fn (array $f) => [
            'food_id' => (int) $f['food_id'],
            'food_name' => $f['food_name'],
            'food_type' => $f['food_type'] ?? 'Generic',
            'brand_name' => $f['brand_name'] ?? null,
        ], $foods);
    }

    /**
     * Detalle completo + intento de autocálculo de nutrición por gramo.
     * `can_autocalculate` es false si ninguna ración devuelta tiene
     * metric_serving_unit en g/ml -- en ese caso NUNCA se debe adivinar una
     * conversión, el admin rellena a mano (ver sección 4.2, regla
     * obligatoria).
     *
     * `food.get.v5` (antes v4, cambiado 2026-09-20 -- ver
     * docs/FATSECRET_INTEGRATION.md sección 13): compatible hacia atrás,
     * mismos nombres de campo por ración -- v5 solo AÑADE una ración
     * estandarizada "100 g"/"100 ml" (`serving_id=0`) para alimentos de
     * marca que en v4 podían no tener ninguna ración nativa en gramos.
     * Verificado en real que no rompe nada existente (probado food_id=5110).
     *
     * @return array{food_id:int, food_name_en:string, serving_id:?int, can_autocalculate:bool, calories_per_gram:?float, protein_per_gram:?float, fat_per_gram:?float, carbs_per_gram:?float, density_hint:?float}
     */
    public function detail(int $foodId, string $region = 'US'): array
    {
        $data = $this->client->call('food.get.v5', [
            'food_id' => $foodId,
            'region' => $region,
        ]);

        $food = $data['food'] ?? [];
        $servings = $this->normalizeList($food['servings']['serving'] ?? []);

        $usable = null;
        $unitIsVolume = false;
        foreach ($servings as $serving) {
            $unit = strtolower($serving['metric_serving_unit'] ?? '');
            if ($unit === 'g') {
                $usable = $serving;
                $unitIsVolume = false;
                break;
            }
            if ($unit === 'ml' && $usable === null) {
                // Preferir 'g' si aparece más adelante en la lista -- solo
                // usar 'ml' si ninguna ración en gramos aparece.
                $usable = $serving;
                $unitIsVolume = true;
            }
        }

        $result = [
            'food_id' => (int) ($food['food_id'] ?? $foodId),
            'food_name_en' => $food['food_name'] ?? '',
            'serving_id' => null,
            'can_autocalculate' => false,
            'calories_per_gram' => null,
            'protein_per_gram' => null,
            'fat_per_gram' => null,
            'carbs_per_gram' => null,
            'density_hint' => null,
        ];

        if ($usable === null) {
            return $result;
        }

        $metricAmount = (float) $usable['metric_serving_amount'];
        if ($metricAmount <= 0) {
            return $result;
        }

        // Si la ración usable es en 'ml', el gramaje real depende de la
        // densidad del ingrediente -- eso lo aplica quien llama (el admin
        // ya tiene el campo `density` del Ingredient), aquí solo se avisa
        // con density_hint para que el frontend no lo trate como gramos
        // puros sin más.
        $result['serving_id'] = (int) $usable['serving_id'];
        $result['can_autocalculate'] = true;
        $result['calories_per_gram'] = round(((float) $usable['calories']) / $metricAmount, 6);
        $result['protein_per_gram'] = round(((float) ($usable['protein'] ?? 0)) / $metricAmount, 6);
        $result['fat_per_gram'] = round(((float) ($usable['fat'] ?? 0)) / $metricAmount, 6);
        $result['carbs_per_gram'] = round(((float) ($usable['carbohydrate'] ?? 0)) / $metricAmount, 6);
        $result['density_hint'] = $unitIsVolume ? 1.0 : null;

        return $result;
    }

    /**
     * Recalcula usando la MISMA ración ya vinculada (fatsecret_serving_id) --
     * usado por el comando de refresco, nunca por el flujo de creación.
     */
    public function recalculateWithServing(int $foodId, int $servingId, string $region = 'US'): ?array
    {
        $data = $this->client->call('food.get.v5', [
            'food_id' => $foodId,
            'region' => $region,
        ]);

        $servings = $this->normalizeList($data['food']['servings']['serving'] ?? []);
        foreach ($servings as $serving) {
            if ((int) $serving['serving_id'] !== $servingId) {
                continue;
            }
            $metricAmount = (float) ($serving['metric_serving_amount'] ?? 0);
            if ($metricAmount <= 0) {
                return null;
            }

            return [
                'calories_per_gram' => round(((float) $serving['calories']) / $metricAmount, 6),
                'protein_per_gram' => round(((float) ($serving['protein'] ?? 0)) / $metricAmount, 6),
                'fat_per_gram' => round(((float) ($serving['fat'] ?? 0)) / $metricAmount, 6),
                'carbs_per_gram' => round(((float) ($serving['carbohydrate'] ?? 0)) / $metricAmount, 6),
            ];
        }

        return null;
    }

    /**
     * FatSecret (convertido de XML) devuelve un objeto asociativo en vez de
     * un array cuando solo hay un elemento en una lista -- esto normaliza
     * siempre a una lista indexada de 0..n, sea 0, 1 o N elementos.
     */
    private function normalizeList(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }
        // Lista ya indexada numéricamente (0,1,2...) -> ya es un array de items.
        if (array_is_list($value)) {
            return $value;
        }
        // Objeto único (claves asociativas tipo food_id/food_name...) -> envolver.
        return [$value];
    }
}
