<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpenFoodFactsService
{
    private string $baseUrl = 'https://world.openfoodfacts.org/api/v2';

    private const MAX_RETRIES = 2;
    private const RETRY_DELAY_MS = 500;

    public function searchProduct(string $query): ?array
    {
        $cacheKey = 'off_search_' . md5(strtolower($query));

        return Cache::remember($cacheKey, 3600, function () use ($query) {
            try {
                $response = Http::timeout(10)
                    ->withoutVerifying()
                    ->retry(self::MAX_RETRIES, self::RETRY_DELAY_MS)
                    ->get("{$this->baseUrl}/search", [
                        'search_terms'  => $query,
                        'search_simple' => 1,
                        'action'        => 'process',
                        'json'          => 1,
                        'page_size'     => 3,
                    ]);

                if ($response->successful()) {
                    $products = $response->json('products', []);
                    if (!empty($products)) {
                        return $this->extractNutrients($products[0]);
                    }
                }

                return null;
            } catch (\Exception $e) {
                Log::warning("OpenFoodFacts search error for: {$query}", [
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    public function getProductByBarcode(string $barcode): ?array
    {
        $cacheKey = "off_product_{$barcode}";

        return Cache::remember($cacheKey, 86400, function () use ($barcode) {
            try {
                $response = Http::timeout(10)
                    ->withoutVerifying()
                    ->retry(self::MAX_RETRIES, self::RETRY_DELAY_MS)
                    ->get("{$this->baseUrl}/product/{$barcode}");

                if ($response->successful() && $response->json('status') === 1) {
                    return $this->extractNutrients($response->json('product', []));
                }

                return null;
            } catch (\Exception $e) {
                Log::warning("OpenFoodFacts barcode error: {$barcode}", [
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    private function extractNutrients(array $product): ?array
    {
        $nutriments = $product['nutriments'] ?? [];
        if (empty($nutriments)) {
            return null;
        }

        $servingSize = 100;
        if (isset($product['serving_size'])) {
            preg_match('/(\d+[\.,]?\d*)/', $product['serving_size'], $m);
            if (!empty($m[1])) {
                $servingSize = (float) str_replace(',', '.', $m[1]);
            }
        }

        $factor = $servingSize > 0 ? (100 / $servingSize) : 1;

        return [
            'calories_per_gram' => round(($nutriments['energy-kcal_100g'] ?? 0) / 100, 4),
            'protein_per_gram'  => round(($nutriments['proteins_100g'] ?? 0) / 100, 4),
            'fat_per_gram'      => round(($nutriments['fat_100g'] ?? 0) / 100, 4),
            'carbs_per_gram'    => round(($nutriments['carbohydrates_100g'] ?? 0) / 100, 4),
            'fiber_per_100g'    => round($nutriments['fiber_100g'] ?? 0, 2),
            'sugar_per_100g'    => round($nutriments['sugars_100g'] ?? 0, 2),
            'sodium_per_100g'   => round($nutriments['sodium_100g'] ?? 0, 2),
            'description'       => $product['product_name'] ?? null,
            'brand'             => $product['brands'] ?? null,
        ];
    }
}
