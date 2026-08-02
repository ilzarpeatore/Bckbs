<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Recipe;

$base = __DIR__ . '/storage/app/chatcocina/Datasets/Recetas';
$files = glob("$base/*.csv");

echo "=== COUNTRY CSVs ===\n";
echo str_pad("FILE", 30) . str_pad("TOTAL", 8) . str_pad("NEW", 8) . "TITLES\n";
echo str_repeat("-", 100) . "\n";

sort($files);
foreach ($files as $file) {
    $name = basename($file);
    $h = fopen($file, 'r');
    $headers = fgetcsv($h);
    
    $titleIdx = array_search('title', array_map('strtolower', $headers));
    if ($titleIdx === false) $titleIdx = 1;
    
    $total = 0;
    $titles = [];
    while ($row = fgetcsv($h)) {
        $total++;
        $t = trim($row[$titleIdx] ?? '');
        if ($t) $titles[] = $t;
    }
    fclose($h);
    
    $existingTitles = Recipe::pluck('title')->map(fn($t) => strtolower(trim($t)))->toArray();
    $existingSet = array_flip($existingTitles);
    
    $overlap = 0;
    foreach ($titles as $t) {
        if (isset($existingSet[strtolower(trim($t))])) $overlap++;
    }
    
    $new = $total - $overlap;
    $sampleTitles = array_slice($titles, 0, 3);
    
    echo str_pad($name, 30) . str_pad($total, 8) . str_pad($new, 8);
    echo implode(' | ', $sampleTitles) . "\n";
}
