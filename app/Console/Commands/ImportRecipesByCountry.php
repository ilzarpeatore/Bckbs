<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use App\Models\RecipeTag;
use App\Models\RecipeTagMapping;
use Illuminate\Console\Command;

class ImportRecipesByCountry extends Command
{
    protected $signature = 'import:recipes-by-country';
    protected $description = 'Import per-country CSVs, keep all Spain, 50 per other country';

    private const BASE_DIR = 'C:\Users\hamza\AppData\Local\Temp\opencode\recipes';

    private const FILES = [
        'alemania' => '🇩🇪 Alemania', 'arabes' => '🥙 Árabe', 'argentina' => '🇦🇷 Argentina',
        'australia' => '🇦🇺 Australia', 'austria' => '🇦🇹 Austria', 'bolivia' => '🇧🇴 Bolivia',
        'brasil' => '🇧🇷 Brasil', 'bulgaria' => '🇧🇬 Bulgaria', 'chile' => '🇨🇱 Chile',
        'china' => '🇨🇳 China', 'colombia' => '🇨🇴 Colombia', 'costa_rica' => '🇨🇷 Costa Rica',
        'dinamarca' => '🇩🇰 Dinamarca', 'ecuador' => '🇪🇨 Ecuador', 'egipto' => '🇪🇬 Egipto',
        'estados_unidos' => '🇺🇸 EEUU', 'estonia' => '🇪🇪 Estonia',
        'finlandia' => '🇫🇮 Finlandia', 'grecia' => '🇬🇷 Grecia', 'hungria' => '🇭🇺 Hungría',
        'india' => '🇮🇳 India', 'indonesia' => '🇮🇩 Indonesia', 'inglaterra' => '🇬🇧 Inglaterra',
        'israel' => '🇮🇱 Israel', 'italia' => '🇮🇹 Italia', 'japon' => '🇯🇵 Japón',
        'libia' => '🇱🇾 Libia', 'mexico' => '🇲🇽 México', 'noruega' => '🇳🇴 Noruega',
        'paises_bajos' => '🇳🇱 Países Bajos', 'portugal' => '🇵🇹 Portugal', 'puerto_rico' => '🇵🇷 Puerto Rico',
        'reino_unido' => '🇬🇧 Reino Unido', 'rumania' => '🇷🇴 Rumanía', 'suecia' => '🇸🇪 Suecia',
        'tailandia' => '🇹🇭 Tailandia', 'uruguay' => '🇺🇾 Uruguay', 'venezuela' => '🇻🇪 Venezuela',
    ];

    private const SPAIN_FILES = ['espana_1', 'espana_2', 'espana_3'];

    public function handle(): int
    {
        foreach (self::FILES as $tag) {
            RecipeTag::firstOrCreate(['title' => $tag], ['status' => 1]);
        }
        RecipeTag::firstOrCreate(['title' => '🇪🇸 España'], ['status' => 1]);

        // Spain: all recipes
        $spainCount = 0;
        foreach (self::SPAIN_FILES as $file) {
            $path = self::BASE_DIR . "/{$file}.csv";
            if (!file_exists($path)) { $this->warn("Missing: {$file}"); continue; }
            $count = $this->importFile($path, '🇪🇸 España', null);
            $spainCount += $count;
            $this->info("  {$file}: {$count} recipes");
        }

        // Internals (Spanish generic)
        for ($i = 1; $i <= 5; $i++) {
            $path = self::BASE_DIR . "/internas_{$i}.csv";
            if (!file_exists($path)) continue;
            $count = $this->importFile($path, '🇪🇸 España', null);
            $spainCount += $count;
            $this->info("  internas_{$i}: {$count} recipes");
        }

        // Other countries: 50 each
        $totalOther = 0;
        foreach (self::FILES as $file => $tag) {
            $path = self::BASE_DIR . "/{$file}.csv";
            if (!file_exists($path)) { $this->warn("Missing: {$file}"); continue; }
            $count = $this->importFile($path, $tag, 50);
            $totalOther += $count;
            $this->info("  {$file}: {$count} recipes");
        }

        $this->info("Done! Spain: {$spainCount}, Other: {$totalOther}, Total: " . Recipe::count());
        return 0;
    }

    private function importFile(string $path, string $tag, ?int $limit): int
    {
        $handle = @fopen($path, 'r');
        if (!$handle) return 0;

        fgetcsv($handle); // skip header
        $count = 0;
        $tagModel = RecipeTag::where('title', $tag)->first();

        while (($row = fgetcsv($handle)) !== false) {
            if ($limit !== null && $count >= $limit) break;
            if (count($row) < 2) continue;

            $title = substr(trim($row[1] ?? $row[0] ?? ''), 0, 255);
            if (empty($title) || strlen($title) < 3 || strlen($title) > 200) continue;

            $ingredients = trim($row[3] ?? '');
            $steps = trim($row[4] ?? '');
            $desc = "INGREDIENTES:\n{$ingredients}\n\nPREPARACIÓN:\n{$steps}";
            $prepTime = min(120, max(5, (int) (strlen($steps) / 25)));

            try {
                $recipe = Recipe::create([
                    'title' => $title,
                    'description' => mb_substr($desc, 0, 5000),
                    'preparation_time' => $prepTime,
                    'status' => 1,
                    'is_premium' => false,
                ]);

                if ($tagModel) {
                    RecipeTagMapping::create([
                        'recipe_id' => $recipe->id,
                        'recipe_tag_id' => $tagModel->id,
                    ]);
                }
                $count++;
            } catch (\Exception $e) {
                // skip malformed rows
                continue;
            }
        }

        fclose($handle);
        return $count;
    }
}
