<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$with = App\Models\Ingredient::where('calories_per_gram', '>', 0)->count();
$without = App\Models\Ingredient::where('calories_per_gram', 0)->count();
$recipesWithMacros = App\Models\Recipe::where('calories', '>', 0)->count();
$totalRecipes = App\Models\Recipe::count();
$totalIngredients = App\Models\Ingredient::count();
echo "=== ESTADO ACTUAL ===\n";
echo "Ingredientes con nutrición: $with\n";
echo "Ingredientes sin nutrición: $without\n";
echo "Recetas con macros: $recipesWithMacros / $totalRecipes\n";
echo "Total ingredientes: $totalIngredients\n";

// Muestra 5 ingredientes con nutrición
$sample = App\Models\Ingredient::where('calories_per_gram', '>', 0)->limit(5)->get();
echo "\n=== EJEMPLOS ===\n";
foreach ($sample as $ing) {
    echo $ing->title . ": " . round($ing->calories_per_gram * 100, 1) . " kcal/100g\n";
}

// Verifica si hay recetas
$recipeSample = App\Models\Recipe::with('recipeIngredients')->limit(3)->get();
echo "\n=== EJEMPLOS DE RECETAS ===\n";
foreach ($recipeSample as $r) {
    echo $r->title . " | " . $r->calories . " kcal | " . $r->recipeIngredients->count() . " ingredientes\n";
}
