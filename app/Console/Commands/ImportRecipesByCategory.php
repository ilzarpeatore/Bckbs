<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use App\Models\RecipeTag;
use App\Models\RecipeTagMapping;
use Illuminate\Console\Command;

class ImportRecipesByCategory extends Command
{
    protected $signature = 'import:recipes-by-category';
    protected $description = 'Import recipes from local Datasets/Recetas1/ organized by food type';

    private const BASE_DIR = 'C:\Users\hamza\AppData\Local\Temp\opencode\categories';

    private const CATEGORIES = [
        'Ensaladas'   => ['🥗 Ensaladas', 'all'],
        'Carne'       => ['🥩 Carne', 100],
        'Pescado'     => ['🐟 Pescado', 100],
        'Pasta'       => ['🍝 Pasta', 100],
        'Sopas'       => ['🍲 Sopas', 100],
        'Guisos'      => ['🍲 Guisos y potajes', 100],
        'Arroces'     => ['🍚 Arroces y cereales', 'all'],
        'Verduras'    => ['🥦 Verduras', 'all'],
        'Aves'        => ['🍗 Aves y caza', 50],
        'Mariscos'    => ['🦐 Mariscos', 50],
        'Legumbres'   => ['🫘 Legumbres', 50],
        'Huevos'      => ['🥚 Huevos y lácteos', 50],
        'Pan'         => ['🥖 Pan y bollería', 50],
        'Postres'     => ['🍰 Postres', 50],
        'Cocteles'    => ['🍸 Cócteles y bebidas', 50],
        'Salsas'      => ['🥣 Salsas', 50],
        'Aperitivos'  => ['🍢 Aperitivos y tapas', 50],
    ];

    public function handle(): int
    {
        foreach (self::CATEGORIES as [$tag,]) {
            RecipeTag::firstOrCreate(['title' => $tag], ['status' => 1]);
        }

        $totalImported = 0;
        $totalSkipped = 0;

        foreach (self::CATEGORIES as $folder => [$tag, $limit]) {
            $dir = self::BASE_DIR . '/' . $folder;
            if (!is_dir($dir)) { $this->warn("Missing: {$folder}"); continue; }

            $catImported = 0;
            $tagModel = RecipeTag::where('title', $tag)->first();
            $files = glob("{$dir}/*.csv");
            sort($files, SORT_NATURAL);

            foreach ($files as $file) {
                if ($limit !== 'all' && $catImported >= $limit) break;

                $handle = @fopen($file, 'r');
                if (!$handle) continue;
                fgetcsv($handle);

                while (($row = fgetcsv($handle)) !== false) {
                    if ($limit !== 'all' && $catImported >= $limit) break;
                    if (count($row) < 2) continue;

                    $title = substr(trim($row[0] ?? ''), 0, 255);
                    if (empty($title) || strlen($title) < 3 || strlen($title) > 200) continue;

                    // Recetas1 format: title(0), intro(1), steps(2), votes(3), comensales(4), duracion(5), para(6), dificultad(7), coste(8), types(9), ingredientes(10), url(11)
                    $intro = trim($row[1] ?? '');
                    $stepsRaw = trim($row[2] ?? '');
                    $duration = trim($row[5] ?? '');
                    $types = $row[9] ?? '';
                    $ingredientsArr = $row[10] ?? '';
                    
                    $prepTime = 15;
                    if (preg_match('/(\d+)\s*m/', $duration, $m)) $prepTime = (int) $m[1];
                    elseif (preg_match('/(\d+)\s*h/', $duration, $m)) $prepTime = (int) $m[1] * 60;
                    $prepTime = min(480, max(5, $prepTime));
                    
                    $typeTags = [];
                    if ($types && $types !== '[]') {
                        $decoded = @json_decode(str_replace("'", '"', $types), true);
                        if (is_array($decoded)) $typeTags = $decoded;
                    }
                    
                    $descParts = [];
                    if ($intro) $descParts[] = $intro;
                    if ($stepsRaw && $stepsRaw !== '[]') {
                        $steps = @json_decode(str_replace("'", '"', $stepsRaw), true);
                        if (is_array($steps)) $descParts[] = "PREPARACIÓN:\n" . implode("\n", $steps);
                        else $descParts[] = "PREPARACIÓN:\n" . $stepsRaw;
                    }
                    if ($ingredientsArr && $ingredientsArr !== '[]') {
                        $ing = @json_decode(str_replace("'", '"', $ingredientsArr), true);
                        if (is_array($ing)) $descParts[] = "INGREDIENTES:\n" . implode("\n", $ing);
                        else $descParts[] = "INGREDIENTES:\n" . $ingredientsArr;
                    }
                    $desc = implode("\n\n", $descParts);

                    try {
                        $recipe = Recipe::firstOrNew(['title' => $title]);
                        $isNew = !$recipe->exists;
                        $recipe->fill([
                            'description' => mb_substr($desc, 0, 5000),
                            'preparation_time' => $prepTime, 'status' => 1, 'is_premium' => false,
                        ]);
                        $recipe->save();

                        if ($tagModel) RecipeTagMapping::firstOrCreate(['recipe_id' => $recipe->id, 'recipe_tag_id' => $tagModel->id]);
                        foreach ($typeTags as $tt) {
                            $ttag = RecipeTag::firstOrCreate(['title' => $tt], ['status' => 1]);
                            RecipeTagMapping::firstOrCreate(['recipe_id' => $recipe->id, 'recipe_tag_id' => $ttag->id]);
                        }
                        $catImported++;
                        if (!$isNew) $totalSkipped++;
                    } catch (\Exception $e) { continue; }
                }
                fclose($handle);
            }

            $this->info("  {$tag}: {$catImported}" . ($limit !== 'all' ? " (limit: {$limit})" : " (all)"));
            $totalImported += $catImported;
        }

        $this->info("Done! Imported: {$totalImported}, Skipped: {$totalSkipped}, Total: " . Recipe::count());
        return 0;
    }
}
