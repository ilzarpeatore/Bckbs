<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\MeasurementUnit;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeCategoryMapping;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\RecipeTag;
use App\Models\RecipeTagMapping;
use App\Services\IngredientMappingService;
use App\Services\IngredientParserService;
use App\Services\OpenFoodFactsService;
use App\Services\UsdaNutritionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportChatCocinaCommand extends Command
{
    protected $signature = 'chatcocina:import
                            {--phase=all : Phase to run: all, recipes, ingredients, nutrition, tags}
                            {--limit=0 : Max recipes to import (0=all)}
                            {--dry-run : Show what would be imported without saving}
                            {--clear : Delete all ChatCocina-imported recipes before importing}';

    protected $description = 'Import ChatCocina recipes with ingredients, nutrition, and tags';

    private IngredientParserService $parser;
    private UsdaNutritionService $usda;
    private OpenFoodFactsService $off;

    private int $importedCount = 0;
    private int $skippedCount = 0;
    private int $errorCount = 0;
    private int $ingredientsCreated = 0;
    private int $nutritionUpdated = 0;

    private array $ingredientCache = [];
    private array $unitCache = [];
    private array $categoryCache = [];
    private array $ingredientCategoryCache = [];

    private const SOURCE_TAG = 'Importado ChatCocina';
    private const BATCH_SIZE = 200;

    public function __construct(IngredientParserService $parser, UsdaNutritionService $usda, OpenFoodFactsService $off)
    {
        parent::__construct();
        $this->parser = $parser;
        $this->usda = $usda;
        $this->off = $off;
    }

    public function handle(): int
    {
        $phase = $this->option('phase');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        $clear = $this->option('clear');

        $this->info('ChatCocina Import Command');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be saved.');
            $this->newLine();
        }

        $this->loadCaches();

        if ($clear && !$dryRun) {
            $this->clearExistingData();
        }

        $this->createTags();

        if (in_array($phase, ['all', 'recipes'])) {
            $this->importRecipes($limit, $dryRun);
        }

        if (in_array($phase, ['all', 'nutrition'])) {
            $this->fetchNutritionData($dryRun);
        }

        $this->printSummary();
        return 0;
    }

    private function loadCaches(): void
    {
        $this->unitCache = MeasurementUnit::all()->mapWithKeys(function ($unit) {
            return [$unit->symbol => [
                'id' => $unit->id,
                'symbol' => $unit->symbol,
                'unit_type' => $unit->unit_type,
                'base_conversion_factor' => (float) $unit->base_conversion_factor,
            ]];
        })->toArray();
        $this->ingredientCache = Ingredient::pluck('id', 'slug')->toArray();
        $this->categoryCache = IngredientCategory::pluck('id', 'title')->toArray();
        $this->ingredientCategoryCache = IngredientCategory::pluck('id', 'title')->toArray();

        $this->info("Loaded caches: " . count($this->unitCache) . " units, " .
            count($this->ingredientCache) . " ingredients, " .
            count($this->categoryCache) . " categories.");
    }

    private function clearExistingData(): void
    {
        $this->warn('Clearing existing ChatCocina imported recipes...');

        $tagId = RecipeTag::where('title', self::SOURCE_TAG)->value('id');
        if ($tagId) {
            $recipeIds = RecipeTagMapping::where('recipe_tag_id', $tagId)->pluck('recipe_id')->toArray();
            if (!empty($recipeIds)) {
                Recipe::whereIn('id', $recipeIds)->delete();
                $this->info("Deleted " . count($recipeIds) . " existing imported recipes.");
            }
        }
    }

    private function importRecipes(int $limit, bool $dryRun): void
    {
        $this->info('--- Phase 1: Importing Recipes ---');

        $csvFiles = $this->getSelectedCsvFiles();
        $this->info("Found " . count($csvFiles) . " CSV files to process.");

        $categories = $this->ensureRecipeCategories($csvFiles);
        $tagId = $this->ensureImportTag();

        $totalImported = 0;

        foreach ($csvFiles as $source => $filePath) {
            if ($limit > 0 && $totalImported >= $limit) {
                break;
            }

            $this->info("Processing: {$source}");

            $rows = $this->readCsvFile($filePath);
            $categoryId = $categories[$source] ?? null;

            $batch = [];
            foreach ($rows as $row) {
                if ($limit > 0 && $totalImported >= $limit) {
                    break;
                }

                $title = $this->extractField($row, ['title']);
                if (empty($title) || mb_strlen($title) < 3) {
                    $this->skippedCount++;
                    continue;
                }

                $existing = Recipe::where('title', $title)->first();
                if ($existing) {
                    $this->skippedCount++;
                    continue;
                }

                $batch[] = [
                    'title'       => trim($title),
                    'description' => $this->extractField($row, ['description', 'intro']),
                    'type'        => $this->guessRecipeType($row),
                    'meal_type'   => json_encode([$this->guessMealType($row)]),
                    'status'      => 'active',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                    '_source'     => $source,
                    '_steps'      => $this->extractField($row, ['steps', 'step']),
                    '_ingredients' => $this->extractField($row, ['ingredients', 'ingredientes']),
                ];

                $totalImported++;

                if (count($batch) >= self::BATCH_SIZE) {
                    $this->flushRecipeBatch($batch, $categoryId, $tagId, $dryRun);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $this->flushRecipeBatch($batch, $categoryId, $tagId, $dryRun);
            }
        }

        $this->info("Phase 1 complete: {$this->importedCount} imported, {$this->skippedCount} skipped, {$this->errorCount} errors.");
    }

    private function flushRecipeBatch(array $batch, ?int $categoryId, int $tagId, bool $dryRun): void
    {
        if ($dryRun) {
            foreach ($batch as $item) {
                $this->line("  [DRY] Would create: {$item['title']}");
            }
            return;
        }

        DB::transaction(function () use (&$batch, $categoryId, $tagId) {
            foreach ($batch as $item) {
                $stepsText = $item['_steps'];
                $ingredientsText = $item['_ingredients'];

                unset($item['_steps'], $item['_ingredients'], $item['_source']);

                try {
                    $recipe = Recipe::create($item);

                    if ($categoryId) {
                        RecipeCategoryMapping::create([
                            'recipe_id'          => $recipe->id,
                            'recipe_category_id' => $categoryId,
                        ]);
                    }

                    RecipeTagMapping::create([
                        'recipe_id'     => $recipe->id,
                        'recipe_tag_id' => $tagId,
                    ]);

                    $this->saveSteps($recipe->id, $stepsText);
                    $this->saveIngredients($recipe, $ingredientsText);

                    $this->importedCount++;
                } catch (\Exception $e) {
                    $this->errorCount++;
                }
            }
        });
    }

    private function saveIngredients(Recipe $recipe, ?string $ingredientsText): void
    {
        if (empty($ingredientsText)) {
            return;
        }

        $parsed = $this->parser->parseIngredientsText($ingredientsText);
        if (empty($parsed)) {
            return;
        }

        $totalCal = $totalPro = $totalFat = $totalCarb = 0;

        foreach ($parsed as $item) {
            $ingredient = $this->findOrCreateIngredient($item['name']);
            if (!$ingredient) {
                continue;
            }

            $unit = $this->resolveUnit($item['unit']);
            $unitId = $unit['id'] ?? null;
            $quantity = $item['quantity'] ?? 1;

            $grams = $this->calculateGrams($quantity, $unit, $ingredient);

            $cal = $grams * $ingredient->calories_per_gram;
            $pro = $grams * $ingredient->protein_per_gram;
            $fat = $grams * $ingredient->fat_per_gram;
            $carb = $grams * $ingredient->carbs_per_gram;

            RecipeIngredient::create([
                'recipe_id'           => $recipe->id,
                'ingredient_id'       => $ingredient->id,
                'measurement_unit_id' => $unitId,
                'quantity'            => $quantity,
                'quantity_grams'      => $grams,
                'amount'              => $unit['base_conversion_factor'] ?? 1,
                'calories'            => $cal,
                'protein'             => $pro,
                'fats'                => $fat,
                'carbs'               => $carb,
            ]);

            $totalCal += $cal;
            $totalPro += $pro;
            $totalFat += $fat;
            $totalCarb += $carb;
        }

        if ($totalCal > 0 || $totalPro > 0) {
            $recipe->update([
                'calories' => round($totalCal, 2),
                'protein'  => round($totalPro, 2),
                'fats'     => round($totalFat, 2),
                'carbs'    => round($totalCarb, 2),
            ]);
        }
    }

    private function saveSteps(int $recipeId, ?string $stepsText): void
    {
        if (empty($stepsText)) {
            return;
        }

        $stepsText = str_replace('\n', "\n", $stepsText);
        $stepsText = html_entity_decode($stepsText, ENT_QUOTES, 'UTF-8');
        $stepsText = strip_tags($stepsText);

        $steps = preg_split('/[\n\r]+/', $stepsText);
        $steps = array_filter(array_map(function ($s) {
            $s = trim($s, " \t\n\r\0\x0B0123456789.:-");
            return mb_strlen($s) > 5 ? $s : null;
        }, $steps));

        $sequence = 1;
        foreach ($steps as $step) {
            RecipeStep::create([
                'recipe_id'    => $recipeId,
                'instruction'  => $step,
                'sequence'     => $sequence++,
            ]);
        }
    }

    private function findOrCreateIngredient(string $name): ?Ingredient
    {
        $name = trim($name);
        if (empty($name) || mb_strlen($name) < 2) {
            return null;
        }

        $slug = Str::slug($name);

        if (isset($this->ingredientCache[$slug])) {
            return Ingredient::find($this->ingredientCache[$slug]);
        }

        $existing = Ingredient::whereRaw('LOWER(title) = LOWER(?)', [$name])->first();

        if ($existing) {
            $this->ingredientCache[Str::slug($existing->title)] = $existing->id;
            return $existing;
        }

        // Use the mapping service to determine the canonical name, category, and macros
        $mapped = IngredientMappingService::getMacrosForName($name);
        $mappedName = $mapped['name'];
        $mappedSlug = Str::slug($mappedName);

        // Check if a canonical ingredient already exists
        if (isset($this->ingredientCache[$mappedSlug])) {
            return Ingredient::find($this->ingredientCache[$mappedSlug]);
        }

        $existingMapped = Ingredient::whereRaw('LOWER(title) = LOWER(?)', [$mappedName])->first();
        if ($existingMapped) {
            $this->ingredientCache[$mappedSlug] = $existingMapped->id;
            $this->ingredientCache[$slug] = $existingMapped->id;
            return $existingMapped;
        }

        $categoryId = $this->ingredientCategoryCache[$mapped['category']] ?? null;
        if (!$categoryId) {
            $categoryId = $this->guessIngredientCategory($mappedName);
        }

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

        $this->ingredientCache[$mappedSlug] = $ingredient->id;
        $this->ingredientCache[$slug] = $ingredient->id;
        $this->ingredientsCreated++;

        return $ingredient;
    }

    private function resolveUnit(?string $symbol): array
    {
        $default = $this->unitCache['pc'] ?? ['id' => null, 'symbol' => 'pc', 'unit_type' => 'count', 'base_conversion_factor' => 1.0];

        if (empty($symbol)) {
            return $default;
        }

        $symbol = strtolower(trim($symbol));

        $aliases = [
            'kg' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg',
            'g' => 'g', 'gr' => 'g', 'gramo' => 'g', 'gramos' => 'g',
            'oz' => 'oz', 'onzas' => 'oz', 'onza' => 'oz',
            'lb' => 'lb', 'libras' => 'lb', 'libra' => 'lb',
            'l' => 'L', 'litro' => 'L', 'litros' => 'L',
            'ml' => 'ml', 'mililitro' => 'ml', 'mililitros' => 'ml',
            'cup' => 'cup', 'taza' => 'cup', 'tazas' => 'cup',
            'tbsp' => 'tbsp', 'cda' => 'tbsp', 'cdas' => 'tbsp', 'cucharada' => 'tbsp', 'cucharadas' => 'tbsp',
            'tsp' => 'tsp', 'cdta' => 'tsp', 'cdtas' => 'tsp', 'cucharadita' => 'tsp', 'cucharaditas' => 'tsp',
            'pc' => 'pc', 'pieza' => 'pc', 'piezas' => 'pc',
            'slice' => 'slice', 'rodaja' => 'slice', 'rodajas' => 'slice', 'rebanada' => 'slice', 'rebanadas' => 'slice',
            'unit' => 'unit', 'unidad' => 'unit', 'unidades' => 'unit',
            'pinch' => 'pinch', 'pizca' => 'pinch',
            'cloves' => 'cloves', 'diente' => 'cloves', 'dientes' => 'cloves',
        ];

        $mapped = $aliases[$symbol] ?? $symbol;

        return $this->unitCache[$mapped] ?? $default;
    }

    private function calculateGrams(float $quantity, array $unit, Ingredient $ingredient): float
    {
        $unitType = $unit['unit_type'] ?? 'count';
        $factor = (float) ($unit['base_conversion_factor'] ?? 1.0);
        $density = (float) ($ingredient->density ?? 1.0);

        if ($unitType === 'weight') {
            return $quantity * $factor;
        }

        if ($unitType === 'volume') {
            return $quantity * $factor * $density;
        }

        // Count: approximate based on ingredient density
        // For liquids, 1 unit ≈ 10g; for dense items, 1 unit ≈ 30g; for light items, 1 unit ≈ 5g
        $defaultGramPerUnit = $density >= 0.8 ? 30 : 10;
        return $quantity * $defaultGramPerUnit;
    }

    private function guessIngredientCategory(string $name): ?int
    {
        $name = mb_strtolower($name);

        $categoryKeywords = [
            'Proteins'         => ['pollo', 'carne', 'res', 'cerdo', 'pavo', 'jamón', 'jamon', 'atún', 'atun', 'salmón', 'salmon', 'huevos', 'huevo', 'bacalao', 'camarones', 'pescado', 'ternera', 'chorizo', 'tocino', 'pechuga', 'lomo', 'costilla', 'albondiga', 'albóndiga', 'cecina', 'longaniza', 'morcilla', 'salchicha'],
            'Vegetables'       => ['tomate', 'cebolla', 'ajo', 'zanahoria', 'papa', 'lechuga', 'pepino', 'chile', 'pimiento', 'brócoli', 'brocoli', 'espinaca', 'calabaza', 'ejotes', 'champiñones', 'champinones', 'apio', 'coliflor', 'berenjena', 'jitomate', 'nopal', 'elote', 'maíz', 'maiz', 'aguacate', 'palta', 'rábano', 'remolacha', 'betabel', 'acelgas', 'alcachofa', 'espárrago'],
            'Fruits'           => ['manzana', 'plátano', 'platano', 'naranja', 'limón', 'limon', 'fresa', 'fresas', 'mango', 'piña', 'pina', 'uva', 'pera', 'melocotón', 'cereza', 'sandía', 'melon', 'papaya', 'coco', 'guayaba', 'maracuyá', 'toronja', 'pomelo', 'higo', 'durazno', 'ciruela', 'mandarina', 'lima', 'lila'],
            'Grains & Pasta'   => ['arroz', 'pasta', 'fideos', 'espagueti', 'macarrones', 'pan', 'tortilla', 'harina', 'avena', 'cereal', 'quinoa', 'trigo', 'maíz', 'maiz', 'cebada', 'centeno', 'almidón', 'galleta', 'pancake', 'waffle', 'pizza', 'taco', 'burrito', 'empanada', 'arepa', 'ñoquis', 'ñoquis'],
            'Dairy'            => ['leche', 'queso', 'yogur', 'yogurt', 'mantequilla', 'crema', 'nata', 'suero', 'ricotta', 'mozzarella', 'parmesano', 'cheddar', 'quesillo', 'requesón', 'dulce de leche', 'helado'],
            'Fats & Oils'      => ['aceite', 'oliva', 'vegetal', 'margarina', 'grasa', 'manteca', 'aguate'],
            'Nuts & Seeds'     => ['nuez', 'nueces', 'almendra', 'almendras', 'maní', 'mani', 'cacahuate', 'avellana', 'pistacho', 'castaña', 'semilla', 'ajonjolí', 'linaza', 'chía', 'chia', 'girasol', 'nuez de la india', 'piñón'],
            'Legumes'          => ['frijol', 'frijoles', 'lenteja', 'lentejas', 'garbanzo', 'garbanzos', 'habichuela', 'negritos', 'alubia', 'poroto', 'soya', 'tofu', 'tempeh'],
            'Spices & Herbs'   => ['sal', 'pimienta', 'comino', 'orégano', 'oregano', 'perejil', 'cilantro', 'albahaca', 'romero', 'tomillo', 'curry', 'canela', 'jengibre', 'pimentón', 'paprika', 'nuez moscada', ' laurel', 'hojas de laurel', 'achiote', 'annatto'],
            'Condiments & Sauces' => ['salsa', 'vinagre', 'mostaza', 'ketchup', 'mayonesa', 'soya', 'worcestershire', 'tabasco', 'sriracha', 'bbq', 'aderezo', 'catsup'],
            'Sweeteners'       => ['azúcar', 'azucar', 'miel', 'panela', 'stevia', 'aspartame', 'sacarina', 'jarabe', 'melaza'],
        ];

        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($name, $keyword)) {
                    return $this->categoryCache[$category] ?? null;
                }
            }
        }

        return $this->categoryCache['Processed Foods'] ?? null;
    }

    private function createTags(): void
    {
        $this->info('--- Creating Import Tags ---');

        $tags = [
            self::SOURCE_TAG,
            'Rápido y Fácil',
            'Alto en Proteína',
            'Bajo en Carbohidratos',
            'Vegetariano',
            'Vegano',
            'Sin Gluten',
            'Sin Lácteos',
            'Desayuno',
            'Almuerzo',
            'Cena',
            'Merienda',
            'Postre',
        ];

        foreach ($tags as $title) {
            RecipeTag::firstOrCreate(
                ['title' => $title],
                ['status' => 'active']
            );
        }

        $this->info("Ensured " . count($tags) . " recipe tags exist.");
    }

    private function fetchNutritionData(bool $dryRun): void
    {
        $this->info('--- Phase 3: Fetching Nutrition Data ---');

        $hasUsda = $this->usda->isConfigured();
        if ($hasUsda) {
            $this->info('Using USDA FoodData Central API.');
        } else {
            $this->warn('USDA API key not configured. Using OpenFoodFacts (free, no key needed).');
        }

        $ingredients = Ingredient::where('calories_per_gram', 0)
            ->where('status', 'active')
            ->limit(1000)
            ->get();

        $this->info("Found " . count($ingredients) . " ingredients without nutrition data.");

        $bar = $this->output->createProgressBar(count($ingredients));
        $bar->start();

        foreach ($ingredients as $ingredient) {
            $nutrients = null;

            if ($hasUsda) {
                $nutrients = $this->usda->searchAndGetNutrients($ingredient->title);
            }

            if (!$nutrients) {
                $nutrients = $this->off->searchProduct($ingredient->title);
            }

            if ($nutrients && !$dryRun) {
                $ingredient->update([
                    'calories_per_gram' => $nutrients['calories_per_gram'] ?? 0,
                    'protein_per_gram'  => $nutrients['protein_per_gram'] ?? 0,
                    'fat_per_gram'      => $nutrients['fat_per_gram'] ?? 0,
                    'carbs_per_gram'    => $nutrients['carbs_per_gram'] ?? 0,
                    'density'           => 1.0,
                ]);
                $this->nutritionUpdated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Phase 3 complete: {$this->nutritionUpdated} ingredients updated with USDA data.");
    }

    private function getSelectedCsvFiles(): array
    {
        $basePath = storage_path('app/chatcocina');
        $files = [];

        $priorityFiles = [
            'Clean/train.csv'                   => 'Recetas Variadas',
            'Clean/valid.csv'                   => 'Recetas Variadas',
            'Clean/test.csv'                    => 'Recetas Variadas',
        ];

        foreach ($priorityFiles as $relativePath => $category) {
            $fullPath = $basePath . '/' . $relativePath;
            if (File::exists($fullPath)) {
                $files[$category] = $fullPath;
            }
        }

        $gourmetPath = $basePath . '/Datasets/el_gourmet_chile';
        if (File::isDirectory($gourmetPath)) {
            foreach (File::files($gourmetPath) as $file) {
                if ($file->getExtension() === 'csv') {
                    $files['Chilena'] = $file->getRealPath();
                }
            }
        }

        $colombianPath = $basePath . '/Datasets/mycolombianrecipes';
        if (File::isDirectory($colombianPath)) {
            foreach (File::files($colombianPath) as $file) {
                if ($file->getExtension() === 'csv') {
                    $files['Colombiana'] = $file->getRealPath();
                }
            }
        }

        $barraPath = $basePath . '/Datasets/Barra';
        if (File::isDirectory($barraPath)) {
            foreach (File::files($barraPath) as $file) {
                if ($file->getExtension() === 'csv') {
                    $files['Argentina'] = $file->getRealPath();
                }
            }
        }

        return $files;
    }

    private function ensureRecipeCategories(array $csvFiles): array
    {
        $categories = [];
        foreach (array_keys($csvFiles) as $name) {
            $cat = RecipeCategory::firstOrCreate(
                ['title' => $name],
                ['status' => 'active']
            );
            $categories[$name] = $cat->id;
        }
        return $categories;
    }

    private function ensureImportTag(): int
    {
        $tag = RecipeTag::firstOrCreate(
            ['title' => self::SOURCE_TAG],
            ['status' => 'active']
        );
        return $tag->id;
    }

    private function readCsvFile(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [];
        }

        $headers = array_map('strtolower', array_map('trim', $headers));

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        fclose($handle);
        return $rows;
    }

    private function extractField(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && !empty(trim($row[$key]))) {
                return trim($row[$key]);
            }
        }
        return null;
    }

    private function guessRecipeType(array $row): string
    {
        $ingredients = strtolower($this->extractField($row, ['ingredients', 'ingredientes']) ?? '');
        $title = strtolower($this->extractField($row, ['title']) ?? '');

        $meatWords = ['pollo', 'carne', 'res', 'cerdo', 'pavo', 'jamón', 'jamon', 'atún', 'atun', 'salmón', 'salmon', 'bacalao', 'camarones', 'pescado', 'ternera', 'chorizo', 'tocino', 'jamón', 'huevos'];
        foreach ($meatWords as $word) {
            if (Str::contains($ingredients, $word) || Str::contains($title, $word)) {
                return 'non-veg';
            }
        }

        $veganWords = ['leche', 'queso', 'yogur', 'mantequilla', 'crema', 'huevo', 'huevos'];
        foreach ($veganWords as $word) {
            if (Str::contains($ingredients, $word)) {
                return 'veg';
            }
        }

        return 'vegan';
    }

    private function guessMealType(array $row): string
    {
        $title = mb_strtolower($this->extractField($row, ['title']) ?? '');
        $ingredients = mb_strtolower($this->extractField($row, ['ingredients', 'ingredientes']) ?? '');
        $all = $title . ' ' . $ingredients;

        if (Str::contains($all, ['desayuno', 'breakfast', 'avena', 'cereal', 'tostada', 'panqueque', 'waffle', 'jugo natural'])) {
            return 'breakfast';
        }
        if (Str::contains($all, ['postre', 'dessert', 'pastel', 'torta', 'galleta', 'helado', 'brownie', 'flan', 'natilla'])) {
            return 'snacks';
        }
        if (Str::contains($all, ['cena', 'dinner', 'sopa'])) {
            return 'dinner';
        }

        return 'lunch';
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('=== IMPORT SUMMARY ===');
        $this->info("Recipes imported:    {$this->importedCount}");
        $this->info("Recipes skipped:     {$this->skippedCount}");
        $this->info("Errors:              {$this->errorCount}");
        $this->info("Ingredients created: {$this->ingredientsCreated}");
        $this->info("Nutrition updated:   {$this->nutritionUpdated}");
        $this->newLine();

        $totalRecipes = Recipe::count();
        $totalIngredients = Ingredient::count();
        $totalSteps = RecipeStep::count();
        $totalRecipeIngredients = RecipeIngredient::count();
        $this->info("DB totals: {$totalRecipes} recipes, {$totalIngredients} ingredients, {$totalSteps} steps, {$totalRecipeIngredients} recipe-ingredients.");
    }
}
