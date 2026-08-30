<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Command;
use App\Services\RecipeParserService;

class CalculateRecipeMacros extends Command
{
    protected $signature = 'recipes:calculate-macros';
    protected $description = 'Calculate calories, protein, carbs, fats for all recipes (per serving)';

    public function __construct(private RecipeParserService $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        [$ingredientForms, $allForms] = $this->parser->buildIngredientIndexes();
        $total = Recipe::count();
        $updated = 0; $skipped = 0;

        $this->info("{$total} recipes, " . count($ingredientForms) . " ingredients");

        Recipe::chunk(200, function ($recipes) use ($ingredientForms, $allForms, &$updated, &$skipped) {
            foreach ($recipes as $recipe) {
                $text = mb_strtolower($recipe->description ?? '');
                $text = $this->parser->stripAccents($text);

                if (!str_contains($text, 'ingredientes')) { $skipped++; continue; }

                [$ingSection, $isFallback] = $this->parser->extractIngredientSection($text);

                $lines = $this->parser->expandLines($ingSection, $allForms);
                $totalCal = 0; $totalPro = 0; $totalCarbs = 0; $totalFat = 0; $totalGrams = 0;
                $found = 0;

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || strlen($line) < 3) continue;

                    if ($isFallback) {
                        if (preg_match('/^(nutricion|informacion nutricional|valor nutricional|comensales)/iu', $line)) break;
                    } elseif ($this->parser->isStepStart($line)) {
                        break;
                    }

                    if ($this->parser->isActionLine($line)) continue;

                    $grams = $this->parser->parseQuantity($line);

                    $matched = $this->parser->matchIngredient($line, $ingredientForms);

                    $grams = $this->parser->resolveGrams($line, $grams, $matched);

                    if ($grams === 0) continue;

                    if ($matched) {
                        $totalCal += $matched->calories_per_gram * $grams;
                        $totalPro += $matched->protein_per_gram * $grams;
                        $totalCarbs += $matched->carbs_per_gram * $grams;
                        $totalFat += $matched->fat_per_gram * $grams;
                        $totalGrams += $grams;
                        $found++;
                    }
                }

                // Servings: explicit when available, otherwise estimate from total weight
                $hasExplicit = false;
                $servings = 1;
                $servingsText = $recipe->title . ' ' . $text;
                if (preg_match('/(\d+)\s*comensales/iu', $servingsText, $sm)) { $servings = max(1, (int) $sm[1]); $hasExplicit = true; }
                elseif (preg_match('/(\d+)\s*personas/iu', $servingsText, $sm)) { $servings = max(1, (int) $sm[1]); $hasExplicit = true; }
                elseif (preg_match('/(\d+)\s*raciones/iu', $servingsText, $sm)) { $servings = max(1, (int) $sm[1]); $hasExplicit = true; }

                if (!$hasExplicit && $totalGrams > 0) {
                    $servings = $this->parser->estimateServings($servingsText, $totalGrams);
                }

                if ($found > 0 && $totalCal > 0) {
                    $perCal = round($totalCal / $servings);
                    $perPro = round($totalPro / $servings, 1);
                    $perCarbs = round($totalCarbs / $servings, 1);
                    $perFat = round($totalFat / $servings, 1);

                    if ($perCal >= 20 && $perCal <= 2000
                        && $perPro >= 0 && $perPro <= 120
                        && $perCarbs >= 0 && $perCarbs <= 250
                        && $perFat >= 0 && $perFat <= 120) {

                        $recipe->updateQuietly([
                            'calories' => $perCal,
                            'protein' => $perPro,
                            'carbs' => $perCarbs,
                            'fats' => $perFat,
                        ]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Updated: {$updated}, Skipped: {$skipped}");
        $this->info("With macros: " . Recipe::where('calories', '>', 0)->count());
        return 0;
    }
}
