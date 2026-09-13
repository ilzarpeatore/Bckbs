<?php
// SEGURIDAD (revision 2026-09-13): script de mantenimiento puntual, pensado
// para ejecutarse por CLI (php reset_macros.php). Si por un error de despliegue
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
DB::table('recipes')->update(['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fats' => 0]);
echo 'reset done, recipes with macros: ' . DB::table('recipes')->where('calories', '>', 0)->count() . PHP_EOL;
