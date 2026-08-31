<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use App\Models\RecipeTag;
use App\Models\RecipeTagMapping;
use Illuminate\Console\Command;

class ImportRecipesAll extends Command
{
    protected $signature = 'import:recipes-all';
    protected $description = 'Rebuild recipes from clean ChatCocina CSVs (country + category), INGREDIENTES-first format';

    private const COUNTRY_DIR = 'C:\Users\hamza\AppData\Local\Temp\opencode\recipes';
    private const CATEGORY_DIR = 'C:\Users\hamza\AppData\Local\Temp\opencode\categories';

    private const COUNTRIES = [
        'alemania.csv' => '🇩🇪 Alemania', 'arabes.csv' => '🥙 Árabe', 'argentina.csv' => '🇦🇷 Argentina',
        'australia.csv' => '🇦🇺 Australia', 'austria.csv' => '🇦🇹 Austria', 'bolivia.csv' => '🇧🇴 Bolivia',
        'brasil.csv' => '🇧🇷 Brasil', 'bulgaria.csv' => '🇧🇬 Bulgaria', 'chile.csv' => '🇨🇱 Chile',
        'china.csv' => '🇨🇳 China', 'colombia.csv' => '🇨🇴 Colombia', 'costa_rica.csv' => '🇨🇷 Costa Rica',
        'dinamarca.csv' => '🇩🇰 Dinamarca', 'ecuador.csv' => '🇪🇨 Ecuador', 'egipto.csv' => '🇪🇬 Egipto',
        'estados_unidos.csv' => '🇺🇸 EEUU', 'estonia.csv' => '🇪🇪 Estonia',
        'finlandia.csv' => '🇫🇮 Finlandia', 'grecia.csv' => '🇬🇷 Grecia', 'hungria.csv' => '🇭🇺 Hungría',
        'india.csv' => '🇮🇳 India', 'indonesia.csv' => '🇮🇩 Indonesia', 'inglaterra.csv' => '🇬🇧 Inglaterra',
        'israel.csv' => '🇮🇱 Israel', 'italia.csv' => '🇮🇹 Italia', 'japon.csv' => '🇯🇵 Japón',
        'libia.csv' => '🇱🇾 Libia', 'mexico.csv' => '🇲🇽 México', 'noruega.csv' => '🇳🇴 Noruega',
        'paises_bajos.csv' => '🇳🇱 Países Bajos', 'portugal.csv' => '🇵🇹 Portugal', 'puerto_rico.csv' => '🇵🇷 Puerto Rico',
        'reino_unido.csv' => '🇬🇧 Reino Unido', 'rumania.csv' => '🇷🇴 Rumanía', 'suecia.csv' => '🇸🇪 Suecia',
        'tailandia.csv' => '🇹🇭 Tailandia', 'uruguay.csv' => '🇺🇾 Uruguay', 'venezuela.csv' => '🇻🇪 Venezuela',
    ];

    private const SPAIN_FILES = ['espana_1.csv', 'espana_2.csv', 'espana_3.csv'];

    private const CATEGORIES = [
        'Ensaladas'   => ['🥗 Ensaladas', 'all'],
        'Carne'       => ['🥩 Carne', 100],
        'Pescado'     => ['🐟 Pescado', 100],
        'Pasta'       => ['🍝 Pasta', 100],
        'Sopas'       => ['🍲 Sopas', 100],
        'Guisos_y_potajes' => ['🍲 Guisos y potajes', 100],
        'Arroces_y_cereales' => ['🍚 Arroces y cereales', 'all'],
        'Verduras'    => ['🥦 Verduras', 'all'],
        'Aves_y_caza' => ['🍗 Aves y caza', 50],
        'Mariscos'    => ['🦐 Mariscos', 50],
        'Legumbres'   => ['🫘 Legumbres', 50],
        'Huevos_y_lacteos' => ['🥚 Huevos y lácteos', 50],
        'Pan_y_bolleria' => ['🥖 Pan y bollería', 50],
        'Postres'     => ['🍰 Postres', 50],
        'Cocteles_y_bebidas' => ['🍸 Cócteles y bebidas', 50],
        'Salsas'      => ['🥣 Salsas', 50],
        'Aperitivos_y_tapas' => ['🍢 Aperitivos y tapas', 50],
    ];

    public function handle(): int
    {
        $this->info("Importing country recipes...");
        $countryCount = $this->importCountries();
        $this->info("Country recipes: {$countryCount}");

        $this->info(PHP_EOL . "Importing category recipes...");
        $categoryCount = $this->importCategories();
        $this->info("Category recipes: {$categoryCount}");

        $this->info(PHP_EOL . "Done! Total recipes: " . Recipe::count());
        return 0;
    }

    private function importCountries(): int
    {
        $count = 0;

        // Spain: all recipes
        foreach (self::SPAIN_FILES as $file) {
            $path = self::COUNTRY_DIR . '/' . $file;
            if (!file_exists($path)) { $this->warn("Missing: {$file}"); continue; }
            $count += $this->importCountryFile($path, '🇪🇸 España', null, $file);
        }

        // Internals (Spanish generic)
        for ($i = 1; $i <= 5; $i++) {
            $path = self::COUNTRY_DIR . "/internas_{$i}.csv";
            if (!file_exists($path)) continue;
            $count += $this->importCountryFile($path, '🇪🇸 España', null, "internas_{$i}");
        }

        // Other countries: 50 each
        foreach (self::COUNTRIES as $file => $tag) {
            $path = self::COUNTRY_DIR . '/' . $file;
            if (!file_exists($path)) { $this->warn("Missing: {$file}"); continue; }
            $count += $this->importCountryFile($path, $tag, 50, $file);
        }

        return $count;
    }

    private function importCountryFile(string $path, string $tag, ?int $limit, string $src): int
    {
        $handle = @fopen($path, 'r');
        if (!$handle) return 0;
        fgetcsv($handle); // skip header
        $count = 0;
        $tagModel = RecipeTag::firstOrCreate(['title' => $tag], ['status' => 1]);

        while (($row = fgetcsv($handle)) !== false) {
            if ($limit !== null && $count >= $limit) break;
            if (count($row) < 5) continue;

            $title = trim($row[1] ?? '');
            if (empty($title) || strlen($title) < 3 || strlen($title) > 200) continue;
            // Skip titles that look like cooking steps, not recipe names
            if ($this->looksLikeStep($title)) continue;

            $ingredients = trim($row[3] ?? '');
            $steps = trim($row[4] ?? '');
            if (empty($ingredients) && empty($steps)) continue;

            $desc = "INGREDIENTES:\n{$ingredients}\n\nPREPARACIÓN:\n{$steps}";
            $prepTime = min(120, max(5, (int) (strlen($steps) / 25)));

            $recipe = Recipe::firstOrCreate(
                ['title' => $title],
                [
                    'description' => mb_substr($desc, 0, 20000),
                    'preparation_time' => $prepTime,
                    'status' => 1,
                    'is_premium' => false,
                ]
            );
            if ($recipe->wasRecentlyCreated && $tagModel) {
                RecipeTagMapping::firstOrCreate([
                    'recipe_id' => $recipe->id,
                    'recipe_tag_id' => $tagModel->id,
                ]);
            }
            $count++;
        }
        fclose($handle);
        return $count;
    }

    private function importCategories(): int
    {
        $count = 0;

        foreach (self::CATEGORIES as $folder => [$tag, $limit]) {
            $dir = self::CATEGORY_DIR . '/' . $folder;
            if (!is_dir($dir)) { $this->warn("Missing dir: {$folder}"); continue; }

            $tagModel = RecipeTag::firstOrCreate(['title' => $tag], ['status' => 1]);
            $files = glob("{$dir}/*.csv");
            sort($files, SORT_NATURAL);
            $catCount = 0;

            foreach ($files as $file) {
                if ($limit !== 'all' && $catCount >= $limit) break;
                $handle = @fopen($file, 'r');
                if (!$handle) continue;
                fgetcsv($handle); // skip header

                while (($row = fgetcsv($handle)) !== false) {
                    if ($limit !== 'all' && $catCount >= $limit) break;
                    if (count($row) < 12) continue;

                    $title = trim($row[0] ?? '');
                    if (empty($title) || strlen($title) < 3 || strlen($title) > 200) continue;
                    if ($this->looksLikeStep($title)) continue;

                    $intro = trim($row[1] ?? '');
                    $stepsRaw = trim($row[2] ?? '');
                    $comensales = trim($row[4] ?? '');
                    $duration = trim($row[5] ?? '');
                    $types = $row[9] ?? '';
                    $ingredientsArr = $row[10] ?? '';

                    $steps = $this->decodeArray($stepsRaw);
                    $ingredients = $this->decodeArray($ingredientsArr);
                    $typeTags = $this->decodeArray($types);
                    if (empty($ingredients)) continue;

                    $prepTime = 15;
                    if (preg_match('/(\d+)\s*m/', $duration, $m)) $prepTime = (int) $m[1];
                    elseif (preg_match('/(\d+)\s*h/', $duration, $m)) $prepTime = (int) $m[1] * 60;
                    $prepTime = min(480, max(5, $prepTime));

                    $ingText = implode("\n", $ingredients);
                    $stepsText = is_array($steps) ? implode("\n", $steps) : (string) $steps;

                    // INGREDIENTES FIRST (critical for macro parser), then PREPARACIÓN
                    $descParts = [];
                    $descParts[] = "INGREDIENTES:\n{$ingText}";
                    if ($stepsText) $descParts[] = "PREPARACIÓN:\n{$stepsText}";
                    if ($comensales && preg_match('/\d+/', $comensales)) $descParts[] = $comensales;
                    $desc = implode("\n\n", $descParts);

                    $recipe = Recipe::firstOrCreate(
                        ['title' => $title],
                        [
                            'description' => mb_substr($desc, 0, 20000),
                            'preparation_time' => $prepTime,
                            'status' => 1,
                            'is_premium' => false,
                        ]
                    );
                    if ($recipe->wasRecentlyCreated && $tagModel) {
                        RecipeTagMapping::firstOrCreate([
                            'recipe_id' => $recipe->id,
                            'recipe_tag_id' => $tagModel->id,
                        ]);
                    }
                    if ($recipe->wasRecentlyCreated) {
                        foreach ($typeTags as $tt) {
                            if (!is_string($tt) || trim($tt) === '') continue;
                            $ttag = RecipeTag::firstOrCreate(['title' => $tt], ['status' => 1]);
                            RecipeTagMapping::firstOrCreate(['recipe_id' => $recipe->id, 'recipe_tag_id' => $ttag->id]);
                        }
                    }
                    $catCount++;
                }
                fclose($handle);
            }

            $this->info("  {$tag}: {$catCount}" . ($limit !== 'all' ? " (limit {$limit})" : " (all)"));
            $count += $catCount;
        }

        return $count;
    }

    private function decodeArray(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '[]') return [];
        $decoded = @json_decode($raw, true);
        if (is_array($decoded)) return $decoded;
        // fallback: try replacing single quotes
        $decoded = @json_decode(str_replace("'", '"', $raw), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function looksLikeStep(string $title): bool
    {
        $lower = mb_strtolower(trim($title));
        if (mb_strlen($lower) <= 20) return false; // short titles are fine
        // Step fragments typically start with a cooking verb
        if (preg_match('/^(verter|machacar|limpiar|cortar|pelar|mezclar|batir|echar|poner|añadir|agregar|calentar|hervir|freír|freir|saltear|rehogar|cocinar|hornear|dejar|reservar|servir|espolvorear|precalentar|colocar|remover|revolver|sazonar|salpimentar|asar|triturar|picar|llevar|retirar|trocear|rallar|exprimir|escurrir|tapar|destapar|untar|majar|amasar|estirar|congelar|fundir|derretir|incorporar|disolver|preparar|cuando|mientras|una vez|verter)/i', $lower)) {
            return true;
        }
        return false;
    }
}
