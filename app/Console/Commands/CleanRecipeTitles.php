<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CleanRecipeTitles extends Command
{
    protected $signature = 'recipes:clean-titles
                            {--dry-run : Show proposals without updating}
                            {--limit=0 : Max recipes to process (0=all)}
                            {--force : Replace titles even if they look valid}';

    protected $description = 'Use Gemini AI to fix broken/fragment recipe titles based on ingredients and description';

    private int $updated = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private int $processed = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        if (empty($apiKey)) {
            $this->error('GEMINI_API_KEY not set in .env');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be saved.');
            $this->newLine();
        }

        $recipes = $this->getBadTitles($limit);
        $total = $recipes->count();
        $this->info("Found {$total} recipes with potentially bad titles.");
        $this->newLine();

        foreach ($recipes as $recipe) {
            if ($limit > 0 && $this->processed >= $limit) {
                break;
            }

            $this->processed++;
            $proposed = $this->proposeTitle($recipe, $apiKey);

            if ($proposed === null) {
                $this->errors++;
                $this->warn("[{$this->processed}] ERROR: {$recipe->title}");
                continue;
            }

            if ($proposed === $recipe->title) {
                $this->skipped++;
                $this->line("[{$this->processed}] KEEP: \"{$recipe->title}\"");
                continue;
            }

            if ($dryRun) {
                $this->info("[{$this->processed}] PROPOSE: \"{$recipe->title}\" → \"{$proposed}\"");
            } else {
                $old = $recipe->title;
                $recipe->title = $proposed;
                $recipe->save();
                $this->updated++;
                $this->info("[{$this->processed}] UPDATED: \"{$old}\" → \"{$proposed}\"");
            }

            // Rate limit: 10 requests per minute for Gemini free tier
            usleep(6500000); // 6.5 seconds between requests
        }

        $this->newLine();
        $this->printSummary();
        return self::SUCCESS;
    }

    private function getBadTitles(int $limit): \Illuminate\Database\Eloquent\Collection
    {
        return Recipe::where('status', 'active')
            ->where(function ($q) {
                $q->where('title', 'like', 'Receta %')
                  ->orWhere('title', 'like', '¡Con %')
                  ->orWhere('title', 'like', '!Con %')
                  ->orWhere('title', 'like', '¡Fácil%')
                  ->orWhere('title', 'like', '¡Fáciles%')
                  ->orWhere('title', 'like', '¡FÁCIL%')
                  ->orWhere('title', 'like', '¡Rápido%')
                  ->orWhere('title', 'like', '¡Rápida%')
                  ->orWhere('title', 'like', '¡Listas%')
                  ->orWhere('title', 'like', '¡Listo%')
                  ->orWhere('title', 'like', '¡Listos%')
                  ->orWhere('title', 'like', '¡Buenísima%')
                  ->orWhere('title', 'like', '¡Espectacular%')
                  ->orWhere('title', 'like', '¡Jugosa%')
                  ->orWhere('title', 'like', '¡Ligero%')
                  ->orWhere('title', 'like', '¡Gratinado%')
                  ->orWhere('title', 'like', '¡Más%')
                  ->orWhere('title', 'like', '¡Los%')
                  ->orWhere('title', 'like', '¡Crujientes%')
                  ->orWhere('title', 'like', '¡Diferentes%')
                  ->orWhere('title', 'like', '¡Igual%')
                  ->orWhere('title', 'like', '¡3 %')
                  ->orWhere('title', 'regexp', '^¡[^0-9]')
                  ->orWhere('title', 'like', 'Igual de%')
                  ->orWhere('title', 'like', 'Crujientes%')
                  ->orWhereRaw('LENGTH(TRIM(title)) < 8');
            })
            ->orderBy('id')
            ->get();
    }

    private function proposeTitle(Recipe $recipe, string $apiKey): ?string
    {
        $ingredients = $recipe->recipeIngredients()
            ->join('ingredients', 'recipe_ingredients.ingredient_id', '=', 'ingredients.id')
            ->pluck('ingredients.title')
            ->implode(', ');

        $description = mb_substr($recipe->description ?? '', 0, 500);
        $currentTitle = $recipe->title;

        $prompt = "Dada esta receta con título malo o genérico:\n";
        $prompt .= "- Título actual: \"{$currentTitle}\"\n";
        if (!empty($ingredients)) {
            $prompt .= "- Ingredientes: {$ingredients}\n";
        }
        if (!empty($description)) {
            $prompt .= "- Descripción (primeros 500 chars): {$description}\n";
        }
        $prompt .= "\nDevuelve SOLO el nombre real de la receta en español (1-5 palabras, en español, sin comillas ni puntuación extra). Ejemplo: \"Ensalada Malagueña\", \"Tortilla de Patatas\", \"Lasaña Boloñesa\".\n";
        $prompt .= "Si no puedes determinar el nombre real, devuelve el título actual sin cambios.";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 50,
                    ],
                ]
            );

            if ($response->failed()) {
                Log::warning("Gemini API error for recipe {$recipe->id}: " . $response->body());
                return null;
            }

            $body = $response->json();
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = trim($text);
            // Remove quotes if present
            $text = preg_replace('/^["\']+|["\']+$/u', '', $text);

            return !empty($text) ? $text : $currentTitle;
        } catch (\Throwable $e) {
            Log::error("Gemini API exception for recipe {$recipe->id}: " . $e->getMessage());
            return null;
        }
    }

    private function printSummary(): void
    {
        $this->info('═══════════════════════════════════════');
        $this->info('  SUMMARY');
        $this->info('═══════════════════════════════════════');
        $this->info("  Processed: {$this->processed}");
        $this->info("  Updated:   {$this->updated}");
        $this->info("  Kept:      {$this->skipped}");
        $this->info("  Errors:    {$this->errors}");
        $this->info('═══════════════════════════════════════');
    }
}
