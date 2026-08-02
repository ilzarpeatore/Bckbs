<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Recipe, Ingredient, RecipeStep, RecipeIngredient, RecipeCategory, RecipeCategoryMapping, RecipeTag, RecipeTagMapping};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ═══════════════════════════════════════════════
// STEP 1: Check España status
// ═══════════════════════════════════════════════
echo "=== Current Status ===\n";
echo "Recipes: " . Recipe::count() . "\n";
echo "Ingredients: " . Ingredient::count() . "\n";
echo "Recipe_ingredients: " . RecipeIngredient::count() . "\n";

$spanCat = RecipeCategory::where('title', 'Española')->first();
if ($spanCat) {
    $spanCount = RecipeCategoryMapping::where('recipe_category_id', $spanCat->id)->count();
    echo "Española recipes: $spanCount\n";
} else {
    echo "Española category NOT FOUND\n";
}

$zeroIng = Ingredient::where('calories_per_gram', 0)->count();
echo "Ingredients with 0 macros: $zeroIng\n";

$zeroRecipes = Recipe::where('calories', 0)->whereHas('recipeIngredients')->count();
echo "Recipes with ingredients but 0 calories: $zeroRecipes\n";

// ═══════════════════════════════════════════════
// STEP 2: Batch fill ingredient macros (500 at a time)
// ═══════════════════════════════════════════════
echo "\n=== Batch filling ingredient macros ===\n";

$zeroMacros = Ingredient::where('calories_per_gram', 0)->where('status', 'active')->pluck('id')->toArray();
echo "To update: " . count($zeroMacros) . "\n";

$updated = 0;
$batches = array_chunk($zeroMacros, 200);

foreach ($batches as $batch) {
    $ings = Ingredient::whereIn('id', $batch)->get();
    $updates = [];

    foreach ($ings as $ing) {
        $mapped = \App\Services\IngredientMappingService::getMacrosForName($ing->title);
        if ($mapped['calories_per_gram'] > 0) {
            $updates[] = [
                'id' => $ing->id,
                'calories_per_gram' => $mapped['calories_per_gram'],
                'protein_per_gram' => $mapped['protein_per_gram'],
                'fat_per_gram' => $mapped['fat_per_gram'],
                'carbs_per_gram' => $mapped['carbs_per_gram'],
            ];
        }
    }

    foreach ($updates as $u) {
        Ingredient::where('id', $u['id'])->update([
            'calories_per_gram' => $u['calories_per_gram'],
            'protein_per_gram' => $u['protein_per_gram'],
            'fat_per_gram' => $u['fat_per_gram'],
            'carbs_per_gram' => $u['carbs_per_gram'],
        ]);
        $updated++;
    }

    echo "  Batch done: $updated updated so far\n";
}

$stillZero = Ingredient::where('calories_per_gram', 0)->count();
echo "Remaining 0-macro ingredients: $stillZero\n";

// ═══════════════════════════════════════════════
// STEP 3: Recalculate recipe totals (only those with 0 and have ingredients)
// ═══════════════════════════════════════════════
echo "\n=== Recalculate recipe totals ===\n";

$recipeIds = DB::table('recipe_ingredients')
    ->select('recipe_id')
    ->groupBy('recipe_id')
    ->pluck('recipe_id')
    ->toArray();

echo "Recipes with ingredients: " . count($recipeIds) . "\n";

$recalced = 0;
$chunks = array_chunk($recipeIds, 200);

foreach ($chunks as $chunk) {
    $riData = DB::table('recipe_ingredients')
        ->join('ingredients', 'ingredients.id', '=', 'recipe_ingredients.ingredient_id')
        ->whereIn('recipe_ingredients.recipe_id', $chunk)
        ->select(
            'recipe_ingredients.recipe_id',
            DB::raw('SUM(recipe_ingredients.quantity_grams * ingredients.calories_per_gram) as total_cal'),
            DB::raw('SUM(recipe_ingredients.quantity_grams * ingredients.protein_per_gram) as total_pro'),
            DB::raw('SUM(recipe_ingredients.quantity_grams * ingredients.fat_per_gram) as total_fat'),
            DB::raw('SUM(recipe_ingredients.quantity_grams * ingredients.carbs_per_gram) as total_carb')
        )
        ->groupBy('recipe_ingredients.recipe_id')
        ->get();

    foreach ($riData as $row) {
        Recipe::where('id', $row->recipe_id)->update([
            'calories' => round($row->total_cal, 2),
            'protein' => round($row->total_pro, 2),
            'fats' => round($row->total_fat, 2),
            'carbs' => round($row->total_carb, 2),
        ]);
        $recalced++;
    }

    echo "  Recalculated: $recalced\n";
}

echo "Total recalculated: $recalced\n";

// ═══════════════════════════════════════════════
// STEP 4: Tag Halal/Vegano/Vegetariano (bulk)
// ═══════════════════════════════════════════════
echo "\n=== Tag Halal/Vegano/Vegetariano ===\n";

$halalTag = RecipeTag::firstOrCreate(['title' => 'Halal'], ['status' => 'active']);
$veganTag = RecipeTag::firstOrCreate(['title' => 'Vegano'], ['status' => 'active']);
$vegetarianTag = RecipeTag::firstOrCreate(['title' => 'Vegetariano'], ['status' => 'active']);

// Get all ingredient names per recipe in bulk
$allRecipeIds = Recipe::pluck('id')->toArray();
$halalCount = $veganCount = $vegetarianCount = 0;

$chunks = array_chunk($allRecipeIds, 1000);
foreach ($chunks as $chunk) {
    $ingsByRecipe = DB::table('recipe_ingredients')
        ->join('ingredients', 'ingredients.id', '=', 'recipe_ingredients.ingredient_id')
        ->whereIn('recipe_ingredients.recipe_id', $chunk)
        ->select('recipe_ingredients.recipe_id', 'ingredients.title')
        ->get()
        ->groupBy('recipe_id')
        ->map(fn($items) => $items->pluck('title')->map(fn($t) => mb_strtolower($t))->implode(' '));

    $batchHalal = [];
    $batchVegan = [];
    $batchVegetarian = [];

    foreach ($chunk as $recipeId) {
        $names = $ingsByRecipe[$recipeId] ?? '';

        $halalBanned = ['cerdo','jamón','jamon','tocino','bacón','bacon','chorizo','morcilla','longaniza','vino','cerveza','ron','brandy','aguardiente','sangre'];
        $isHalal = true;
        foreach ($halalBanned as $w) {
            if (Str::contains($names, $w)) { $isHalal = false; break; }
        }
        if ($isHalal) $batchHalal[] = ['recipe_id' => $recipeId, 'recipe_tag_id' => $halalTag->id, 'created_at' => now(), 'updated_at' => now()];

        $veganBanned = ['pollo','carne ',' res ','cerdo','pavo','jamón','jamon','atún','atun','salmón','salmon','bacalao','camarones','pescado','ternera','chorizo','tocino','huevo','huevos','leche','queso','yogur','mantequilla','crema','nata',' manteca'];
        $isVegan = true;
        foreach ($veganBanned as $w) {
            if (Str::contains($names, $w)) { $isVegan = false; break; }
        }
        if ($isVegan) $batchVegan[] = ['recipe_id' => $recipeId, 'recipe_tag_id' => $veganTag->id, 'created_at' => now(), 'updated_at' => now()];

        $vegBanned = ['pollo','carne ',' res ','cerdo','pavo','jamón','jamon','atún','atun','salmón','salmon','bacalao','camarones','pescado','ternera','chorizo','tocino','cordero','conejo','pato'];
        $isVegetarian = true;
        foreach ($vegBanned as $w) {
            if (Str::contains($names, $w)) { $isVegetarian = false; break; }
        }
        if ($isVegetarian) $batchVegetarian[] = ['recipe_id' => $recipeId, 'recipe_tag_id' => $vegetarianTag->id, 'created_at' => now(), 'updated_at' => now()];
    }

    if ($batchHalal) {
        DB::table('recipe_tag_mappings')->insertOrIgnore($batchHalal);
        $halalCount += count($batchHalal);
    }
    if ($batchVegan) {
        DB::table('recipe_tag_mappings')->insertOrIgnore($batchVegan);
        $veganCount += count($batchVegan);
    }
    if ($batchVegetarian) {
        DB::table('recipe_tag_mappings')->insertOrIgnore($batchVegetarian);
        $vegetarianCount += count($batchVegetarian);
    }
}

echo "Halal: $halalCount, Vegano: $veganCount, Vegetariano: $vegetarianCount\n";

// ═══════════════════════════════════════════════
// FINAL SUMMARY
// ═══════════════════════════════════════════════
echo "\n═══ RESUMEN FINAL ═══\n";
echo "Recetas: " . Recipe::count() . "\n";
echo "Ingredientes: " . Ingredient::count() . "\n";
echo "Recipe_ingredients: " . RecipeIngredient::count() . "\n";
echo "Steps: " . RecipeStep::count() . "\n";
echo "Recetas con calories > 0: " . Recipe::where('calories', '>', 0)->count() . "\n";
echo "Ingredientes con calories > 0: " . Ingredient::where('calories_per_gram', '>', 0)->count() . "\n";
echo "Ingredientes con 0 macros: " . Ingredient::where('calories_per_gram', 0)->count() . "\n";

$cats = RecipeCategory::withCount('recipes')->orderByDesc('recipes_count')->get();
echo "\nCategorías:\n";
foreach ($cats as $cat) echo "  {$cat->title}: {$cat->recipes_count}\n";

$tags = RecipeTag::withCount('recipes')->orderByDesc('recipes_count')->get();
echo "\nTags:\n";
foreach ($tags as $tag) echo "  {$tag->title}: {$tag->recipes_count}\n";

// Check how many recipes still have no ingredients
$noIngs = Recipe::whereDoesntHave('recipeIngredients')->count();
echo "\nRecetas SIN ingredientes: $noIngs\n";
