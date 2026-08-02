<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\OpenFoodFactsService;

$off = new OpenFoodFactsService();
$result = $off->searchProduct('chicken breast');
echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
