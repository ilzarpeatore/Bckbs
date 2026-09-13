<?php
// SEGURIDAD (revision 2026-09-13): script de mantenimiento puntual, pensado
// para ejecutarse por CLI (php test_parser.php). Si por un error de despliegue
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

use App\Models\Ingredient;
use App\Services\RecipeParserService;

$parser = new RecipeParserService();

echo "== parseNum fractions ==\n";
foreach (['½', '¼', '¾', '1½', '2 ¼', '1/2', '2.5', '3', '⅓'] as $s) {
    echo "'$s' => " . $parser->parseNum($s) . "\n";
}

echo "\n== diente ==\n";
echo "2 dientes de ajo => " . $parser->parseQuantity('2 dientes de ajo') . "g\n";
echo "1 diente de ajo => " . $parser->parseQuantity('1 diente de ajo') . "g\n";

echo "\n== word boundary (Té phantom) ==\n";
[$ingForms, $allForms] = $parser->buildIngredientIndexes();
foreach (['ingredientes', '4 chorizos colorados', '1 cebolla picada', 'Sal', 'Pimienta', 'manteca250 gr. de azúcar'] as $line) {
    $ing = $parser->matchIngredient($line, $ingForms);
    echo "'$line' => " . ($ing ? $ing->title : 'NONE') . "\n";
}

echo "\n== estimateServings pestinio ==\n";
echo "Pestinios 350g => " . $parser->estimateServings('pestinios', 350) . " rac\n";
echo "Pestiños cordobeses 892g => " . $parser->estimateServings('pestinos cordobeses', 892) . " rac\n";
echo "Pestiños granadinos => " . $parser->estimateServings('pestinos granadinos', 500) . " rac\n";
