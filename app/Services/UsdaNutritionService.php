<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UsdaNutritionService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.nal.usda.gov/fdc/v1';

    private const MAX_RETRIES = 2;
    private const RETRY_DELAY_MS = 500;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.usda.api_key', env('USDA_API_KEY', ''));
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function searchFood(string $query, int $pageSize = 5): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $cacheKey = 'usda_search_' . md5(strtolower($query));

        return Cache::remember($cacheKey, 3600, function () use ($query, $pageSize) {
            try {
                $response = Http::timeout(10)
                    ->withoutVerifying()
                    ->retry(self::MAX_RETRIES, self::RETRY_DELAY_MS)
                    ->get("{$this->baseUrl}/foods/search", [
                        'api_key'       => $this->apiKey,
                        'query'         => $query,
                        'pageSize'      => $pageSize,
                        'dataType'      => ['Foundation', 'SR Legacy'],
                        'sortBy'        => 'score',
                        'sortOrder'     => 'desc',
                    ]);

                if ($response->successful()) {
                    return $response->json('foods', []);
                }

                Log::warning("USDA search failed for: {$query}", [
                    'status' => $response->status(),
                ]);
                return [];
            } catch (\Exception $e) {
                Log::warning("USDA search error for: {$query}", [
                    'error' => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    public function getFoodNutrients(int $fdcId): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $cacheKey = "usda_food_{$fdcId}";

        return Cache::remember($cacheKey, 86400, function () use ($fdcId) {
            try {
                $response = Http::timeout(10)
                    ->withoutVerifying()
                    ->retry(self::MAX_RETRIES, self::RETRY_DELAY_MS)
                    ->get("{$this->baseUrl}/food/{$fdcId}", [
                        'api_key' => $this->apiKey,
                    ]);

                if ($response->successful()) {
                    return $this->extractNutrients($response->json());
                }

                return null;
            } catch (\Exception $e) {
                Log::warning("USDA food detail error for fdcId: {$fdcId}", [
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    public function searchAndGetNutrients(string $query): ?array
    {
        $results = $this->searchFood($query, 1);
        if (empty($results)) {
            return null;
        }

        $food = $results[0];
        $fdcId = $food['fdcId'] ?? null;

        if (!$fdcId) {
            return null;
        }

        $nutrients = $this->getFoodNutrients($fdcId);
        if ($nutrients) {
            $nutrients['description'] = $food['description'] ?? $query;
            $nutrients['fdcId'] = $fdcId;
        }

        return $nutrients;
    }

    private function extractNutrients(array $foodData): ?array
    {
        $nutrients = $foodData['foodNutrients'] ?? [];
        if (empty($nutrients)) {
            return null;
        }

        $map = [];
        foreach ($nutrients as $n) {
            $name = strtolower($n['nutrient']['name'] ?? '');
            $map[$name] = $n['amount'] ?? 0;
        }

        $servingSize = $foodData['servingSize'] ?? 100;
        $servingUnit = $foodData['servingSizeUnit'] ?? 'g';
        $factor = 1;
        if (strtolower($servingUnit) === 'g' && $servingSize > 0) {
            $factor = 100 / $servingSize;
        }

        $energyKcal = $map['energy'] ?? $map['energy (atwater general factors)'] ?? 0;
        if (isset($map['energy (kcal)'])) {
            $energyKcal = $map['energy (kcal)'];
        }

        $caloriesPer100 = $energyKcal > 1500 ? $energyKcal / 10 : $energyKcal * $factor;

        return [
            'calories_per_gram'   => round($caloriesPer100 / 100, 4),
            'protein_per_gram'    => round(($map['protein'] ?? 0) * $factor / 100, 4),
            'fat_per_gram'        => round(($map['total lipid (fat)'] ?? 0) * $factor / 100, 4),
            'carbs_per_gram'      => round(($map['carbohydrate, by difference'] ?? 0) * $factor / 100, 4),
            'fiber_per_100g'      => round(($map['fiber, total dietary'] ?? 0) * $factor, 2),
            'sugar_per_100g'      => round(($map['sugars, total including nle'] ?? $map['sugars, total'] ?? 0) * $factor, 2),
            'sodium_per_100g'     => round(($map['sodium, na'] ?? 0) * $factor, 2),
            'serving_size'        => $servingSize,
            'serving_unit'        => $servingUnit,
        ];
    }

    public function bulkSearch(array $queries, int $delayMs = 300): array
    {
        $results = [];
        foreach ($queries as $key => $query) {
            $results[$key] = $this->searchAndGetNutrients($query);
            if ($delayMs > 0 && $key < count($queries) - 1) {
                usleep($delayMs * 1000);
            }
        }
        return $results;
    }
}
