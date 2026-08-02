<?php
$base = __DIR__ . '/storage/app/chatcocina';

echo "=== Clean/train.csv headers + sample ===\n";
$h = fopen("$base/Clean/train.csv", 'r');
$cols = fgetcsv($h);
echo "Columns: " . implode(' | ', $cols) . "\n";
$row1 = fgetcsv($h);
echo "Row 1: " . implode(' | ', $row1) . "\n";
$row2 = fgetcsv($h);
echo "Row 2: " . implode(' | ', $row2) . "\n";
fclose($h);

echo "\n=== Recetas/colombia.csv headers + sample ===\n";
$h = fopen("$base/Datasets/Recetas/colombia.csv", 'r');
$cols = fgetcsv($h);
echo "Columns: " . implode(' | ', $cols) . "\n";
$row1 = fgetcsv($h);
echo "Row 1: " . implode(' | ', $row1) . "\n";
$row2 = fgetcsv($h);
echo "Row 2: " . implode(' | ', $row2) . "\n";
fclose($h);

echo "\n=== Recetas1/Carne sample files ===\n";
$carnePath = "$base/Datasets/Recetas1/Carne";
if (is_dir($carnePath)) {
    $files = glob("$carnePath/*.csv");
    echo "Files in Carne: " . count($files) . "\n";
    if (!empty($files)) {
        $h = fopen($files[0], 'r');
        $cols = fgetcsv($h);
        echo "Columns: " . implode(' | ', $cols) . "\n";
        $row1 = fgetcsv($h);
        echo "Row 1: " . implode(' | ', $row1) . "\n";
        fclose($h);
    }
}

echo "\n=== Nestle/all.csv headers + sample ===\n";
$h = fopen("$base/Datasets/Nestle/all.csv", 'r');
$cols = fgetcsv($h);
echo "Columns: " . implode(' | ', $cols) . "\n";
$row1 = fgetcsv($h);
echo "Row 1: " . implode(' | ', $row1) . "\n";
fclose($h);

echo "\n=== File counts per source ===\n";
$recetas = glob("$base/Datasets/Recetas/*.csv");
echo "Recetas country files: " . count($recetas) . "\n";
foreach ($recetas as $f) {
    $cnt = count(file($f)) - 1;
    echo "  " . basename($f) . ": $cnt rows\n";
}

$recetas1Dirs = glob("$base/Datasets/Recetas1/*", GLOB_ONLYDIR);
echo "Recetas1 category dirs: " . count($recetas1Dirs) . "\n";
foreach ($recetas1Dirs as $d) {
    $csvs = glob("$d/*.csv");
    $total = 0;
    foreach ($csvs as $csv) $total += count(file($csv)) - 1;
    echo "  " . basename($d) . ": " . count($csvs) . " files, $total rows\n";
}
