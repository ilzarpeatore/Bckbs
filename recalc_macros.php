<?php
// SEGURIDAD (revision 2026-09-13): script de mantenimiento puntual, pensado
// para ejecutarse por CLI (php recalc_macros.php). Si por un error de despliegue
// quedara accesible via HTTP (p.ej. document root apuntando a la raiz del
// repo en vez de a public/), esto evita que cualquiera lo dispare desde el
// navegador -- varios de estos scripts leen o modifican datos sin ningun
// control de acceso propio.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only.');
}
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\RecipeParserService;

$parser = new RecipeParserService();
$dryRun = in_array('--dry-run', $_SERVER['argv'] ?? [], true);

$total = 0; $updated = 0; $kept = 0; $noRows = 0;
$before = Recipe::where('calories', '>', 0)->count();

$recipes = Recipe::orderBy('id')->get(['id', 'title', 'description', 'calories', 'protein', 'carbs', 'fats']);

foreach ($recipes as $recipe) {
    $sum = RecipeIngredient::where('recipe_id', $recipe->id)
        ->selectRaw('COALESCE(SUM(quantity_grams),0) as grams, COALESCE(SUM(calories),0) as cal, COALESCE(SUM(protein),0) as pro, COALESCE(SUM(carbs),0) as carb, COALESCE(SUM(fats),0) as fat')
        ->first();

    $grams = (float) $sum->grams;
    $totalCal = (float) $sum->cal;
    $totalPro = (float) $sum->pro;
    $totalCarbs = (float) $sum->carb;
    $totalFat = (float) $sum->fat;

    if ($totalCal <= 0 || $grams <= 0) { $noRows++; continue; }
    $total++;

    $text = mb_strtolower($parser->stripAccents($recipe->description ?? ''));
    $servingsText = $recipe->title . ' ' . $text;

    $servings = 1; $hasExplicit = false;
    if (preg_match('/(\d+)\s*comensales/iu', $servingsText, $sm)) { $servings = max(1, (int) $sm[1]); $hasExplicit = true; }
    elseif (preg_match('/(\d+)\s*personas/iu', $servingsText, $sm)) { $servings = max(1, (int) $sm[1]); $hasExplicit = true; }
    elseif (preg_match('/(\d+)\s*raciones/iu', $servingsText, $sm)) { $servings = max(1, (int) $sm[1]); $hasExplicit = true; }

    if (!$hasExplicit) {
        $servings = $parser->estimateServings($servingsText, $grams);
    }

    $perCal = round($totalCal / $servings);
    $perPro = round($totalPro / $servings, 1);
    $perCarbs = round($totalCarbs / $servings, 1);
    $perFat = round($totalFat / $servings, 1);

    $inRange = $perCal >= 20 && $perCal <= 2000
        && $perPro >= 0 && $perPro <= 120
        && $perCarbs >= 0 && $perCarbs <= 250
        && $perFat >= 0 && $perFat <= 120;

    if (!$inRange) { $kept++; continue; }

    $updated++;
    if (!$dryRun) {
        $recipe->updateQuietly([
            'calories' => $perCal,
            'protein'  => $perPro,
            'carbs'    => $perCarbs,
            'fats'     => $perFat,
        ]);
    }
}

$after = $dryRun ? $before : Recipe::where('calories', '>', 0)->count();
echo "Recipes with rows: {$total}, updated: {$updated}, kept(out of range): {$kept}, no rows: {$noRows}\n";
echo "Recipes with macros: before={$before} after={$after}\n";
if ($dryRun) echo "DRY RUN: nothing persisted.\n";
