<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AssignRecipeImages extends Command
{
    protected $signature = 'recipes:assign-images
                            {--dry-run : Show what would be downloaded without saving}
                            {--limit=0 : Max recipes to process (0=all)}';

    protected $description = 'Assign food photos from Pexels to recipes that have no image';

    private int $assigned = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private int $processed = 0;
    private int $noResults = 0;
    private int $keyIndex = 0;

    private array $apiKeys = [];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->apiKeys = array_filter([
            env('PEXELS_API_KEY'),
            env('PEXELS_API_KEY_2'),
            env('PEXELS_API_KEY_3'),
        ]);

        if (empty($this->apiKeys)) {
            $this->error('No PEXELS_API_KEY found in .env');
            return self::FAILURE;
        }

        $this->info("Using " . count($this->apiKeys) . " Pexels API key(s) for rotation.");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be saved.');
            $this->newLine();
        }

        $recipes = $this->getRecipesWithoutImages($limit);
        $total = $recipes->count();
        $this->info("Found {$total} recipes without images.");
        $this->newLine();

        foreach ($recipes as $recipe) {
            if ($limit > 0 && $this->processed >= $limit) {
                break;
            }

            $this->processed++;
            // Try search by title first
            $imageUrl = $this->searchPexels($recipe->title);

            // Fallback: search by top ingredients
            if ($imageUrl === null) {
                $ingredients = $recipe->recipeIngredients()
                    ->join('ingredients', 'recipe_ingredients.ingredient_id', '=', 'ingredients.id')
                    ->orderByDesc('recipe_ingredients.quantity_grams')
                    ->take(4)
                    ->pluck('ingredients.title')
                    ->implode(' ');

                if (!empty($ingredients)) {
                    $imageUrl = $this->searchPexels($ingredients);
                }
            }

            if ($imageUrl === null) {
                $this->noResults++;
                $this->warn("[{$this->processed}] NO RESULTS: \"{$recipe->title}\"");
                continue;
            }

            if ($dryRun) {
                $this->info("[{$this->processed}] FOUND: \"{$recipe->title}\" → {$imageUrl}");
            } else {
                if ($this->downloadAndAssign($recipe, $imageUrl)) {
                    $this->assigned++;
                    $this->info("[{$this->processed}] ASSIGNED: \"{$recipe->title}\"");
                } else {
                    $this->errors++;
                    $this->warn("[{$this->processed}] FAILED: \"{$recipe->title}\"");
                }
            }

            // Rate limit: ~3s between requests to stay under 200/hr per key
            usleep(3100000);
        }

        $this->newLine();
        $this->printSummary();
        return self::SUCCESS;
    }

    private function getRecipesWithoutImages(int $limit): \Illuminate\Database\Eloquent\Collection
    {
        $recipesWithImages = \DB::table('media')
            ->select('model_id')
            ->where('model_type', Recipe::class)
            ->where('collection_name', 'recipe_image')
            ->pluck('model_id');

        return Recipe::where('status', 'active')
            ->whereNotIn('id', $recipesWithImages)
            ->orderBy('id')
            ->get();
    }

    private function searchPexels(string $title): ?string
    {
        // Clean title for search
        $query = Str::ascii($title);
        $query = preg_replace('/[^\w\s]/u', '', $query);
        $query = trim($query);

        // Try multiple query variations
        $queries = [
            $query . ' food',
            $query . ' receta',
            $query,
        ];

        foreach ($queries as $q) {
            $result = $this->doPexelsSearch($q);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    private function doPexelsSearch(string $query): ?string
    {
        $maxAttempts = count($this->apiKeys);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $apiKey = $this->getNextApiKey();

            try {
                $response = Http::withHeaders([
                    'Authorization' => $apiKey,
                ])->timeout(15)->get('https://api.pexels.com/v1/search', [
                    'query' => $query,
                    'per_page' => 1,
                    'orientation' => 'portrait',
                ]);

                if ($response->status() === 429) {
                    // Rate limited, try next key
                    $this->warn("  Rate limited on key " . ($this->keyIndex + 1) . ", trying next...");
                    continue;
                }

                if ($response->failed()) {
                    Log::warning("Pexels API error: " . $response->body());
                    return null;
                }

                $data = $response->json();
                $photos = $data['photos'] ?? [];

                if (empty($photos)) {
                    return null;
                }

                // Get medium size image
                return $photos[0]['src']['large'] ?? $photos[0]['src']['medium'] ?? null;
            } catch (\Throwable $e) {
                Log::error("Pexels API exception: " . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    private function getNextApiKey(): string
    {
        $key = $this->apiKeys[$this->keyIndex % count($this->apiKeys)];
        $this->keyIndex++;
        return $key;
    }

    private function downloadAndAssign(Recipe $recipe, string $imageUrl): bool
    {
        try {
            $tempDir = storage_path('app/recipe-image-imports');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = "recipe_{$recipe->id}." . $extension;
            $tempPath = $tempDir . '/' . $filename;

            $response = Http::timeout(30)->get($imageUrl);

            if ($response->failed()) {
                return false;
            }

            file_put_contents($tempPath, $response->body());

            if (!file_exists($tempPath) || filesize($tempPath) === 0) {
                return false;
            }

            // Clear existing and add new
            $recipe->clearMediaCollection('recipe_image');
            $recipe->addMedia($tempPath)
                ->usingName($recipe->slug . '.' . $extension)
                ->toMediaCollection('recipe_image');

            // Clean up temp file
            @unlink($tempPath);

            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to assign image for recipe {$recipe->id}: " . $e->getMessage());
            return false;
        }
    }

    private function printSummary(): void
    {
        $this->info('═══════════════════════════════════════');
        $this->info('  SUMMARY');
        $this->info('═══════════════════════════════════════');
        $this->info("  Processed:  {$this->processed}");
        $this->info("  Assigned:   {$this->assigned}");
        $this->info("  No results: {$this->noResults}");
        $this->info("  Errors:     {$this->errors}");
        $this->info('═══════════════════════════════════════');
    }
}
