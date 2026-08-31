<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\MeasurementUnit;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Services\IngredientMappingService;
use App\Services\RecipeParserService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ExtractRecipeIngredientsCommand extends Command
{
    protected $signature = 'recipes:extract-ingredients
        {--dry-run : Report what would be written without persisting}
        {--limit=0 : Only process the first N recipes}
        {--reset : Delete existing recipe_ingredients and recipe_steps first}
        {--only-missing : Skip recipes that already have ingredient rows}';

    protected $description = 'Extract ingredients and steps from recipe descriptions and persist rows';

    private RecipeParserService $parser;
    private array $categoryCache = [];
    private array $ingredientCache = [];
    private array $unitCache = [];

    private int $ingredientsCreated = 0;
    private int $ingredientsMatched = 0;
    private int $linesTotal = 0;
    private int $linesMatched = 0;
    private int $recipesWithRows = 0;
    private int $stepsCreated = 0;
    private int $macrosUpdated = 0;
    private int $macrosKept = 0;
    private int $noSection = 0;

    private const CATEGORY_MAP = [
        'Proteins' => 'proteinas',
        'Vegetables' => 'verduras',
        'Fruits' => 'frutas',
        'Grains & Pasta' => 'cereales',
        'Dairy' => 'lacteos',
        'Fats & Oils' => 'grasas saludables',
        'Nuts & Seeds' => 'frutos secos',
        'Legumes' => 'legumbres',
        'Spices & Herbs' => 'verduras',
        'Condiments & Sauces' => 'verduras',
        'Beverages' => 'frutas',
        'Baked Goods' => 'cereales',
        'Seafood' => 'proteinas',
        'Processed Foods' => 'proteinas',
        'Sweeteners' => 'frutas',
    ];

    private const UNIT_IDS = [
        'kilo' => 1, 'kilos' => 1, 'kg' => 1,
        'g' => 1, 'gr' => 1, 'gramo' => 1, 'gramos' => 1,
        'litro' => 2, 'litros' => 2,
        'ml' => 2, 'mililitro' => 2, 'mililitros' => 2, 'cc' => 2, 'cl' => 2,
        'cucharada' => 4, 'cucharadas' => 4, 'cda' => 4, 'cdas' => 4,
        'cucharadita' => 5, 'cucharaditas' => 5, 'cdta' => 5, 'cdtas' => 5,
        'taza' => 6, 'tazas' => 6,
    ];

    public function __construct()
    {
        parent::__construct();
        $this->parser = new RecipeParserService();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $reset = (bool) $this->option('reset');
        $onlyMissing = (bool) $this->option('only-missing');

        $this->buildCaches();

        if ($reset && !$dryRun) {
            RecipeIngredient::query()->delete();
            RecipeStep::query()->delete();
            $this->info('Deleted existing recipe_ingredients and recipe_steps.');
        }

        [$ingredientForms, $allForms] = $this->parser->buildIngredientIndexes();

        $query = Recipe::orderBy('id');
        if ($limit > 0) {
            $recipes = $query->limit($limit)->get();
            foreach ($recipes as $recipe) {
                $this->processRecipe($recipe, $ingredientForms, $allForms, $dryRun, $onlyMissing);
            }
        } else {
            $query->chunk(200, function ($recipes) use ($ingredientForms, $allForms, $dryRun, $onlyMissing) {
                foreach ($recipes as $recipe) {
                    $this->processRecipe($recipe, $ingredientForms, $allForms, $dryRun, $onlyMissing);
                }
            });
        }

        $this->info('--- Summary ---');
        $this->info("Recipes without ingredient section: {$this->noSection}");
        $this->info("Ingredient lines parsed: {$this->linesTotal}, matched to an ingredient: {$this->linesMatched}");
        $this->info("Existing ingredients reused: {$this->ingredientsMatched}, new ingredients created: {$this->ingredientsCreated}");
        $this->info("Recipes with ingredient rows: {$this->recipesWithRows}");
        $this->info("Steps created: {$this->stepsCreated}");
        $this->info("Recipe macros updated: {$this->macrosUpdated}, kept: {$this->macrosKept}");

        if ($dryRun) {
            $this->warn('DRY RUN: nothing was persisted.');
        }

        return 0;
    }

    private function processRecipe(Recipe $recipe, array $ingredientForms, array $allForms, bool $dryRun, bool $onlyMissing): void
    {
        if ($onlyMissing && RecipeIngredient::where('recipe_id', $recipe->id)->exists()) {
            return;
        }

        $text = mb_strtolower($recipe->description ?? '');
        $text = $this->parser->stripAccents($text);

        if (!str_contains($text, 'ingredientes')) {
            $this->noSection++;
            return;
        }

        [$ingSection, $isFallback] = $this->parser->extractIngredientSection($text);
        $lines = $this->parser->expandLines($ingSection, $allForms);

        $rows = [];
        $totalCal = 0; $totalPro = 0; $totalCarbs = 0; $totalFat = 0; $totalGrams = 0;
        $matchedCount = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) < 3) continue;

            if ($isFallback) {
                if (preg_match('/^(nutricion|informacion nutricional|valor nutricional|comensales)/iu', $line)) break;
            } elseif ($this->parser->isStepStart($line)) {
                break;
            }

            if ($this->parser->isActionLine($line)) continue;

            $this->linesTotal++;

            $grams = $this->parser->parseQuantity($line);
            $ingredient = $this->parser->matchIngredient($line, $ingredientForms);

            if (!$ingredient) {
                $name = $this->parser->extractIngredientName($line);
                if ($name !== '' && mb_strlen($name) >= 2) {
                    $ingredient = $this->findOrCreateIngredient($name, $dryRun);
                }
            } else {
                $this->ingredientsMatched++;
            }

            if (!$ingredient) continue;

            $grams = $this->parser->resolveGrams($line, $grams, $ingredient);
            if ($grams === 0) continue;

            $cal = $grams * $ingredient->calories_per_gram;
            $pro = $grams * $ingredient->protein_per_gram;
            $fat = $grams * $ingredient->fat_per_gram;
            $carb = $grams * $ingredient->carbs_per_gram;

            $rows[] = [
                'recipe_id'           => $recipe->id,
                'ingredient_id'       => $ingredient->id,
                'measurement_unit_id' => $this->resolveUnitId($line),
                'quantity'            => $this->parser->quantityNumber($line),
                'quantity_grams'      => $grams,
                'amount'              => 1,
                'calories'            => $cal,
                'protein'             => $pro,
                'fats'                => $fat,
                'carbs'               => $carb,
            ];

            $totalCal += $cal;
            $totalPro += $pro;
            $totalCarbs += $carb;
            $totalFat += $fat;
            $totalGrams += $grams;
            $matchedCount++;
            $this->linesMatched++;
        }

        if (!empty($rows)) {
            $this->recipesWithRows++;
            if (!$dryRun) {
                RecipeIngredient::insert($rows);
            }
        }

        $steps = $this->parser->extractSteps($recipe->description);
        if (!empty($steps)) {
            $this->stepsCreated += count($steps);
            if (!$dryRun) {
                $now = now();
                $stepRows = array_map(fn($instruction, $seq) => [
                    'recipe_id'   => $recipe->id,
                    'instruction' => $instruction,
                    'sequence'    => $seq,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ], $steps, range(1, count($steps)));
                RecipeStep::insert($stepRows);
            }
        }

        if ($matchedCount === 0 || $totalCal <= 0) return;

        $servings = 1;
        $hasExplicit = false;
        $servingsText = $recipe->title . ' ' . $text;
        if (preg_match('/(\d+)\s*comensales/iu', $servingsText, $sm)) { $servings = max(1, (int) $sm[1]); $hasExplicit = true; }
        elseif (preg_match('/(\d+)\s*personas/iu', $servingsText, $sm)) { $servings = max(1, (int) $sm[1]); $hasExplicit = true; }
        elseif (preg_match('/(\d+)\s*raciones/iu', $servingsText, $sm)) { $servings = max(1, (int) $sm[1]); $hasExplicit = true; }

        if (!$hasExplicit && $totalGrams > 0) {
            $servings = $this->parser->estimateServings($servingsText, $totalGrams);
        }

        $perCal = round($totalCal / $servings);
        $perPro = round($totalPro / $servings, 1);
        $perCarbs = round($totalCarbs / $servings, 1);
        $perFat = round($totalFat / $servings, 1);

        $inRange = $perCal >= 20 && $perCal <= 2000
            && $perPro >= 0 && $perPro <= 120
            && $perCarbs >= 0 && $perCarbs <= 250
            && $perFat >= 0 && $perFat <= 120;

        if (!$inRange) {
            $this->macrosKept++;
            return;
        }

        $this->macrosUpdated++;
        if (!$dryRun) {
            $recipe->updateQuietly([
                'calories' => $perCal,
                'protein'  => $perPro,
                'carbs'    => $perCarbs,
                'fats'     => $perFat,
            ]);
        }
    }

    private function buildCaches(): void
    {
        foreach (IngredientCategory::all() as $cat) {
            $key = $this->parser->stripAccents(mb_strtolower($cat->title));
            $this->categoryCache[$key] = $cat->id;
        }

        foreach (MeasurementUnit::all() as $unit) {
            $this->unitCache[strtolower($unit->symbol)] = $unit->id;
        }
    }

    private function resolveUnitId(string $line): ?int
    {
        $unit = $this->parser->detectUnit($line);
        if ($unit === null) return null;
        return self::UNIT_IDS[$unit] ?? null;
    }

    private function findOrCreateIngredient(string $name, bool $dryRun = false): ?Ingredient
    {
        $name = trim($name);
        if (empty($name) || mb_strlen($name) < 2) return null;

        $slug = Str::slug($name);

        if (isset($this->ingredientCache[$slug])) {
            return $this->ingredientCache[$slug];
        }

        $existing = Ingredient::whereRaw('LOWER(title) = LOWER(?)', [$name])->first();
        if ($existing) {
            $this->ingredientCache[$slug] = $existing;
            $this->ingredientsMatched++;
            return $existing;
        }

        $mapped = IngredientMappingService::getMacrosForName($name);

        // Only create dictionary-known ingredients to avoid polluting the catalog
        if (empty($mapped['matched'])) {
            return null;
        }

        $mappedName = $mapped['name'];
        $mappedSlug = Str::slug($mappedName);

        if (isset($this->ingredientCache[$mappedSlug])) {
            return $this->ingredientCache[$mappedSlug];
        }

        $existingMapped = Ingredient::whereRaw('LOWER(title) = LOWER(?)', [$mappedName])->first();
        if ($existingMapped) {
            $this->ingredientCache[$mappedSlug] = $existingMapped;
            $this->ingredientCache[$slug] = $existingMapped;
            $this->ingredientsMatched++;
            return $existingMapped;
        }

        $categoryId = $this->categoryCache[self::CATEGORY_MAP[$mapped['category']] ?? ''] ?? null;

        if ($dryRun) {
            $ingredient = new Ingredient([
                'title'                  => $mappedName,
                'ingredient_category_id' => $categoryId,
                'calories_per_gram'      => $mapped['calories_per_gram'],
                'protein_per_gram'       => $mapped['protein_per_gram'],
                'fat_per_gram'           => $mapped['fat_per_gram'],
                'carbs_per_gram'         => $mapped['carbs_per_gram'],
            ]);
            $ingredient->id = -1;
        } else {
            $ingredient = Ingredient::create([
                'title'                  => $mappedName,
                'ingredient_category_id' => $categoryId,
                'calories_per_gram'      => $mapped['calories_per_gram'],
                'protein_per_gram'       => $mapped['protein_per_gram'],
                'fat_per_gram'           => $mapped['fat_per_gram'],
                'carbs_per_gram'         => $mapped['carbs_per_gram'],
                'density'                => 1.0,
                'status'                 => 'active',
            ]);
        }

        $this->ingredientCache[$mappedSlug] = $ingredient;
        $this->ingredientCache[$slug] = $ingredient;
        $this->ingredientsCreated++;

        return $ingredient;
    }
}
