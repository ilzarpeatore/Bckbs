<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Ingredient;
use App\Models\IngredientUnitConversion;
use App\Models\MeasurementUnit;
use Illuminate\Support\Facades\DB;

// Grams per 1 unit, matching RecipeParserService::UNIT_GRAMS used at extraction time.
$gramPerUnit = [
    1 => 1,    // Gramos
    2 => 1,    // Mililitros (density=1 in DB)
    3 => 50,   // Unidad
    4 => 15,   // Cucharada
    5 => 5,    // Cucharadita
    6 => 200,  // Taza
];

$units = MeasurementUnit::whereIn('id', array_keys($gramPerUnit))->pluck('id');
$missing = array_diff(array_keys($gramPerUnit), $units->all());
if ($missing) {
    echo 'ERROR: measurement units not found: ' . implode(',', $missing) . PHP_EOL;
    exit(1);
}

$ingredients = Ingredient::where('status', 'active')->pluck('id');
echo 'Ingredients: ' . $ingredients->count() . ', units: ' . count($gramPerUnit) . PHP_EOL;

$dryRun = in_array('--dry-run', $_SERVER['argv'] ?? [], true);

$now = now();
$rows = [];
foreach ($ingredients as $ingredientId) {
    foreach ($gramPerUnit as $unitId => $grams) {
        $rows[] = [
            'ingredient_id'       => $ingredientId,
            'measurement_unit_id' => $unitId,
            'gram_equivalent'     => $grams,
            'created_at'          => $now,
            'updated_at'          => $now,
        ];
    }
}

$countBefore = IngredientUnitConversion::count();
if ($dryRun) {
    echo 'DRY RUN: would insert ' . count($rows) . ' rows (before=' . $countBefore . ')' . PHP_EOL;
    exit(0);
}

DB::table('ingredient_unit_conversions')->insert($rows);
$countAfter = IngredientUnitConversion::count();
echo 'Inserted ' . count($rows) . ' rows. before=' . $countBefore . ' after=' . $countAfter . PHP_EOL;

$withNull = IngredientUnitConversion::whereNull('ingredient_id')->orWhereNull('measurement_unit_id')->count();
echo 'Rows with null refs: ' . $withNull . PHP_EOL;
