<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Recipe;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\RecipeIngredient;
use App\Services\IngredientParserService;

$parser = new IngredientParserService();
$recipe = Recipe::first();
if (!$recipe) { echo "No recipes\n"; exit; }

$ingredientsText = '400 gr de langostinos 2 cdas de yogur natural 50 gr de queso rallado';
$parsed = $parser->parseIngredientsText($ingredientsText);
echo "Parsed " . count($parsed) . " ingredients\n";

$unitCache = MeasurementUnit::pluck('id', 'symbol')->toArray();
echo "Units: " . json_encode($unitCache) . "\n";

foreach ($parsed as $item) {
    echo "Ingredient: " . $item['name'] . " | unit: " . ($item['unit'] ?? 'null') . "\n";
    try {
        $ingredient = Ingredient::firstOrCreate(
            ['title' => $item['name']],
            ['ingredient_category_id' => 1, 'calories_per_gram' => 0, 'protein_per_gram' => 0, 'fat_per_gram' => 0, 'carbs_per_gram' => 0, 'density' => 1.0, 'status' => 'active']
        );
        echo "  -> Created/found ingredient ID: {$ingredient->id}\n";
        
        $unitId = $unitCache[$item['unit']] ?? $unitCache['pc'] ?? null;
        echo "  -> Unit ID: " . ($unitId ?? 'null') . "\n";
        
        $ri = RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'measurement_unit_id' => $unitId,
            'quantity' => $item['quantity'],
            'quantity_grams' => $item['quantity'] * 10,
            'amount' => 1,
            'calories' => 0,
            'protein' => 0,
            'fats' => 0,
            'carbs' => 0,
        ]);
        echo "  -> Created RecipeIngredient ID: {$ri->id}\n";
    } catch (\Exception $e) {
        echo "  -> ERROR: " . $e->getMessage() . "\n";
    }
}
