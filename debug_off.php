<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$response = Illuminate\Support\Facades\Http::timeout(10)
    ->get('https://world.openfoodfacts.org/api/v2/search', [
        'search_terms'  => 'chicken breast',
        'search_simple' => 1,
        'action'        => 'process',
        'json'          => 1,
        'page_size'     => 3,
    ]);

echo "Status: " . $response->status() . "\n";
echo "Body (first 2000 chars): " . substr($response->body(), 0, 2000) . "\n";
