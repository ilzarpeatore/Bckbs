<?php
// SEGURIDAD (revision 2026-09-13): script de mantenimiento puntual, pensado
// para ejecutarse por CLI (php show_recipes.php). Si por un error de despliegue
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

foreach ([1200, 785, 480, 230, 184, 2956, 3072, 2898, 4381] as $id) {
    $r = Recipe::find($id);
    echo '### ' . $r->id . ' ' . $r->title . PHP_EOL;
    echo substr(strip_tags($r->description), 0, 500) . PHP_EOL;
    echo str_repeat('-', 80) . PHP_EOL;
}
