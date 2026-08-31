<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
DB::table('recipes')->update(['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fats' => 0]);
echo 'reset done, recipes with macros: ' . DB::table('recipes')->where('calories', '>', 0)->count() . PHP_EOL;
