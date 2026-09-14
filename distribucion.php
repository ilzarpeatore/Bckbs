<?php
// SEGURIDAD (revision 2026-09-13): script de mantenimiento puntual, pensado
// para ejecutarse por CLI (php distribucion.php). Si por un error de despliegue
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
use Illuminate\Support\Facades\DB;

echo "Recipes with macros: " . DB::table('recipes')->where('calories', '>', 0)->count() . "\n";
echo "Total recipes: " . DB::table('recipes')->count() . "\n\n";

$buckets = [];
foreach ([100, 200, 300, 400, 500, 600, 700, 800, 900, 1000, 1200, 1500, 2000] as $max) {
    $min = $max === 100 ? 0 : $prev;
    $buckets["{$min}-{$max}"] = DB::table('recipes')->where('calories', '>', $min)->where('calories', '<=', $max)->count();
    $prev = $max;
}
foreach ($buckets as $k => $v) echo "kcal {$k}: {$v}\n";

echo "\n--- Worst offenders (validated ones) ---\n";
$worst = DB::table('recipes')->where('calories', '>', 0)->orderByDesc('calories')->limit(15)->get(['id','title','calories','protein','carbs','fats']);
foreach ($worst as $w) echo "{$w->id} | {$w->title} | cal={$w->calories} p={$w->protein} c={$w->carbs} f={$w->fats}\n";
