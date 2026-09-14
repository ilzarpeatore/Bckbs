<?php
// SEGURIDAD (revision 2026-09-13): script de mantenimiento puntual, pensado
// para ejecutarse por CLI (php dedup_ingredients.php). Si por un error de despliegue
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

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $_SERVER['argv'] ?? [], true);

$before = DB::table('recipe_ingredients')->count();

// Keep the lowest id per exact duplicate group (recipe_id, ingredient_id, measurement_unit_id, quantity_grams)
$dupes = DB::select("
    SELECT id
    FROM recipe_ingredients ri
    WHERE EXISTS (
        SELECT 1 FROM recipe_ingredients ri2
        WHERE ri2.recipe_id = ri.recipe_id
          AND ri2.ingredient_id = ri.ingredient_id
          AND ri2.measurement_unit_id <=> ri.measurement_unit_id
          AND ri2.quantity_grams <=> ri.quantity_grams
          AND ri2.id < ri.id
    )
");

$count = count($dupes);
echo "Duplicate rows found: {$count}\n";

if ($dryRun) {
    echo "DRY RUN: would delete {$count} rows. Total rows before={$before}, after=" . ($before - $count) . "\n";
    exit(0);
}

if ($count > 0) {
    $ids = array_map(fn($d) => (int) $d->id, $dupes);
    // Delete in chunks to avoid huge IN() clauses
    foreach (array_chunk($ids, 500) as $chunk) {
        DB::table('recipe_ingredients')->whereIn('id', $chunk)->delete();
    }
}

$after = DB::table('recipe_ingredients')->count();
echo "Total rows: before={$before} after={$after}\n";

// Re-check nulls and per-recipe coverage
$nulls = DB::table('recipe_ingredients')->whereNull('ingredient_id')->count();
$recipes = DB::table('recipe_ingredients')->distinct()->count('recipe_id');
echo "Rows with null ingredient_id: {$nulls}\n";
echo "Recipes with ingredient rows: {$recipes}\n";
