<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\RecipeParserService;

$threshold = (float) ($_SERVER['argv'][1] ?? 700);
$parser = new RecipeParserService();

$recipes = Recipe::where('calories', '>', $threshold)
    ->where('calories', '>', 0)
    ->orderByDesc('calories')
    ->get(['id', 'title', 'calories', 'protein', 'carbs', 'fats', 'description']);

echo "Recipes > {$threshold} kcal: " . $recipes->count() . "\n\n";
echo str_pad('ID', 6) . str_pad('CAL', 8) . str_pad('RACnow', 6) . str_pad('RACnew', 6) . str_pad('RACexp', 6) . str_pad('gTotal', 8) . str_pad('kcal/100', 9) . ' TITLE' . "\n";
echo str_repeat('-', 100) . "\n";

$changeable = 0;
foreach ($recipes as $r) {
    $text = mb_strtolower($parser->stripAccents($r->description ?? ''));
    $title = mb_strtolower($parser->stripAccents($r->title ?? ''));
    $combined = $title . ' ' . $text;

    $sum = RecipeIngredient::where('recipe_id', $r->id)
        ->selectRaw('COALESCE(SUM(quantity_grams),0) as grams, COALESCE(SUM(calories),0) as cal')
        ->first();

    $grams = (float) $sum->grams;
    $totalCal = (float) $sum->cal;

    $explicit = $parser->findExplicitServings($combined);
    $newServings = $explicit ?: $parser->estimateServings($combined, $grams);
    $nowServings = $totalCal > 0 && $r->calories > 0 ? round($totalCal / $r->calories, 2) : 1;

    $per100 = $grams > 0 ? round($totalCal / $grams * 100) : 0;
    $wouldChange = $newServings > 1 && abs($newServings - $nowServings) > 0.1;
    if ($wouldChange) $changeable++;

    echo str_pad((string) $r->id, 6)
        . str_pad((string) $r->calories, 8)
        . str_pad((string) $nowServings, 6)
        . str_pad((string) $newServings, 6)
        . str_pad((string) ($explicit ?: '-'), 6)
        . str_pad((string) round($grams), 8)
        . str_pad((string) $per100, 9)
        . ' ' . mb_substr($r->title, 0, 42)
        . ($wouldChange ? '   <== new servings=' . $newServings : '') . "\n";
}

echo "\nOutliers that would change with title-aware servings: {$changeable}\n";
