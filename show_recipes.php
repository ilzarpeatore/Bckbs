<?php
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
