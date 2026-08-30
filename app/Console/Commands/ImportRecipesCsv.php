<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Command;

class ImportRecipesCsv extends Command
{
    protected $signature = 'import:recipes {file : Path to CSV file}';
    protected $description = 'Import recipes from ChatCocina CSV dataset';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $handle = fopen($file, 'r');
        fgetcsv($handle); // skip header

        $count = 0;
        $mealTypes = ['breakfast', 'lunch', 'snacks', 'dinner'];
        $types = ['veg', 'non-veg', 'vegan'];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;

            $title = substr(trim($row[0]), 0, 255);
            $ingredients = trim($row[2] ?? '');
            $steps = trim($row[3] ?? '');

            $description = "INGREDIENTES:\n{$ingredients}\n\nPREPARACIÓN:\n{$steps}";

            $mealType = $mealTypes[array_rand($mealTypes)];
            $type = $types[array_rand($types)];

            $prepTime = min(120, max(5, strlen($steps) / 30));

            Recipe::create([
                'title' => $title,
                'description' => substr($description, 0, 5000),
                'meal_type' => $mealType,
                'type' => $type,
                'preparation_time' => (int) $prepTime,
                'status' => 1,
                'is_premium' => false,
            ]);

            $count++;
            if ($count % 500 === 0) {
                $this->info("Imported: {$count}");
            }
        }

        fclose($handle);
        $this->info("Done. Total imported: {$count}");
        return 0;
    }
}
