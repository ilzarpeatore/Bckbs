<?php

namespace App\Services;

use App\Models\Ingredient;

class RecipeParserService
{
    public const NUM = '(\d+\s*\/\s*\d+|\d+\s*[½¼¾⅓⅔⅕⅖⅗⅘⅙⅚]|\d+(?:[.,]\d+)?|[½¼¾⅓⅔⅕⅖⅗⅘⅙⅚])';

    // Order matters: earlier units are tried first, but matching is anchored to line start.
    // Text is accent-normalized before parsing, so unit names are ASCII only.
    public const UNIT_GRAMS = [
        'kilo|kilos|kg' => 1000,
        'litro|litros' => 1000,
        'g\b|gr\b|gramo|gramos|ml\b|mililitro|mililitros|cc\b|cl\b' => 1,
        'cucharada|cucharadas|cda|cdas' => 15,
        'cucharadita|cucharaditas|cdta|cdtas' => 5,
        'taza|tazas' => 200,
        'vaso|vasos' => 250,
        'botella|botellas' => 750,
        'copa|copas' => 100,
        'lata|latas' => 150,
        'sobre|sobres' => 20,
        'chorro' => 15,
        'pizca|pellizco|pellizcos' => 1,
        'barrita|barritas' => 30,
        'rodaja|rodajas|loncha|lonchas|filete|filetes' => 80,
        'pechuga|pechugas' => 150,
        'punado|punados|manojo|manojos|ramo|ramos|ramillete' => 30,
        'unidad|unidades|pieza|piezas|hoja|hojas|ramita|ramitas|diente|dientes' => 50,
        'almendra|almendras|nuez|nueces|avellana|avellanas|anacardo|anacardos|cacahuete|cacahuetes|pistacho|pistachos' => 3,
        'huevo|huevos|limon|limones|patata|patatas|tomate|tomates|cebolla|cebollas|pimiento|pimientos|zanahoria|zanahorias|naranja|naranjas|manzana|manzanas|platano|platanos|gamba|gambas|langostino|langostinos|camaron|camarones|calabacin|calabacines|puerro|puerros|berenjena|berenjenas' => 50,
    ];

    public const SECTION_HEADERS = 'preparacion|elaboracion|pasos|para preparar|como hacer|nutricion|comensales';

    public function stripAccents(string $s): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];
        return strtr($s, $map);
    }

    public function ingredientForms(Ingredient $ing): array
    {
        $n = strtolower($this->stripAccents($ing->title));
        $forms = [$n];
        if (preg_match('/[aeiou]$/', $n)) $forms[] = $n . 's';
        elseif ($n !== '' && !preg_match('/[sz]$/', $n)) $forms[] = $n . 'es';
        return array_values(array_unique($forms));
    }

    public function buildIngredientIndexes(): array
    {
        $ingredients = Ingredient::where('calories_per_gram', '>', 0)->get();
        $ingredientForms = [];
        $allForms = [];
        foreach ($ingredients as $ing) {
            $forms = $this->ingredientForms($ing);
            $ingredientForms[] = [$ing, $forms];
            foreach ($forms as $f) {
                if (strlen($f) >= 3) $allForms[] = $f;
            }
        }
        return [$ingredientForms, array_values(array_unique($allForms))];
    }

    public function extractIngredientSection(string $text): array
    {
        // Returns [section, isFallback]
        $ingSection = '';
        if (preg_match('/(?:ingredientes?)\s*:?\s*\n?\s*(.+?)(?:\n\s*\n\s*(?:' . self::SECTION_HEADERS . '))/siu', $text, $m)) {
            $ingSection = $m[1];
        } elseif (preg_match('/(?:ingredientes?)\s*:?\s*\n?\s*(.+?)(\n\s*\n\s*\n)/siu', $text, $m)) {
            $ingSection = $m[1];
        } elseif (preg_match('/(?:ingredientes?)\s*:?\s*(.{50,3000}?)(?:\n\s*\n)/siu', $text, $m)) {
            $ingSection = $m[1];
        } elseif (preg_match('/(?:ingredientes?)\s*:?\s*(.+)/siu', $text, $m)) {
            $ingSection = substr($m[1], 0, 3000);
        }

        $isFallback = false;
        if (empty(trim($ingSection)) || preg_match('/^(?:' . self::SECTION_HEADERS . ')/iu', trim($ingSection))) {
            $ingSection = preg_replace('/ingredientes?\s*:?\s*/iu', '', $text);
            $isFallback = true;
        }

        return [$ingSection, $isFallback];
    }

    public function expandLines(string $section, array $names): array
    {
        $section = preg_replace('/\.\s*-\s*/u', "\n", $section);
        $section = preg_replace('/^\s*-\s*/mu', '', $section);

        $lines = preg_split('/\s*\n\s*/', trim($section));
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = preg_split('/(?:\s+(?=\d))|(?<=[a-z])(?=\d)/iu', $line);
            if (count($parts) === 1) {
                $part = trim($parts[0]);
                if (!preg_match('/\d/u', $part) && strlen($part) > 30 && $this->countKnownIngredients($part, $names) >= 2) {
                    foreach ($this->splitBareIngredients($part, $names) as $p) $result[] = $p;
                } else {
                    $result[] = $part;
                }
                continue;
            }
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') continue;
                if ($result && preg_match('#^[/.,]\s*\d#u', $part)) {
                    $result[count($result) - 1] .= $part;
                    continue;
                }
                if (!preg_match('/\d/u', $part) && strlen($part) > 30 && $this->countKnownIngredients($part, $names) >= 2) {
                    foreach ($this->splitBareIngredients($part, $names) as $p) $result[] = $p;
                } else {
                    $result[] = $part;
                }
            }
        }
        return $result;
    }

    public function quantityNumber(string $line): float
    {
        if (preg_match('/^\s*' . self::NUM . '/u', $line, $qm)) {
            return $this->parseNum($qm[1]);
        }
        return 1.0;
    }

    public function detectUnit(string $line): ?string
    {
        foreach (array_keys(self::UNIT_GRAMS) as $unit) {
            if (preg_match('/^\s*' . self::NUM . '\s*(' . $unit . ')\b/iu', $line, $qm)) {
                return strtolower($qm[2]);
            }
        }
        return null;
    }

    public function parseQuantity(string $line): int
    {
        foreach (self::UNIT_GRAMS as $unit => $mult) {
            if (preg_match('/^\s*' . self::NUM . '\s*(' . $unit . ')\b/iu', $line, $qm)) {
                $qty = $this->parseNum($qm[1]);
                if (preg_match('/\bdiente\b|\bdientes\b/iu', $qm[2])) {
                    return (int) round($qty * 3);
                }
                return (int) round($qty * $mult);
            }
        }        if (preg_match('/^\s*' . self::NUM . '/u', $line, $qm)) {
            $n = $this->parseNum($qm[1]);
            if ($n <= 12) return (int) round($n * 50);
            return min((int) round($n), 2000);
        }
        return 0;
    }

    public function parseNum(string $s): float
    {
        $s = trim($s);
        if (preg_match('/^\d{1,3}(\.\d{3})+(\.\d+)?$/', $s)) $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        if (preg_match('~^(\d+)\s*/\s*(\d+)$~', $s, $m)) {
            return $m[2] != 0 ? $m[1] / $m[2] : 0;
        }
        $fractions = [
            '½' => 0.5, '¼' => 0.25, '¾' => 0.75,
            '⅓' => 1/3, '⅔' => 2/3,
            '⅕' => 0.2, '⅖' => 0.4, '⅗' => 0.6, '⅘' => 0.8,
            '⅙' => 1/6, '⅚' => 5/6,
        ];
        if (preg_match('/^(-?\d+)\s*([½¼¾⅓⅔⅕⅖⅗⅘⅙⅚])$/u', $s, $m)) {
            return (float) $m[1] + ($fractions[$m[2]] ?? 0);
        }
        foreach ($fractions as $frac => $val) {
            if ($s === $frac) return $val;
        }
        return (float) $s;
    }

    public function estimateServings(string $text, float $totalGrams): int
    {
        $isBakery = (bool) preg_match('/(bizcocho|tarta|pastel|galleta|cookie|cookies|muffin|muffins|cupcake|cupcakes|magdalena|magdalenas|brownie|brownies|crepe|crepes|tortita|tortitas|panqueque|granola|barrita|barritas|pastelillo|roscon|ensaimada|bollo|mantecado|natilla|natillas|flan|mousse|helado|helados|pudding|donut|donuts|churro|churros|bunuelo|bunuelos|polvoron|turron|brioche|pan dulce|empanada|hojaldre|croissant|sobao|sobaos|pestino|pestinos|pestinio|pestinios|cookie|trufa|trufas|coquito|coquitos|cajuzinho|cajuzinhos|bombon|bombones|budin|budines|torta|tortas|gateau|gateaux|kuchen|strudel|crumble|rosquilla|rosquillas|torrija|torrijas|barquillo|barquillos|biscuit|biscocho|rosca|roscas|masa frola)|sugar|dulce/iu', $text);
        $isSoup = (bool) preg_match('/(sopa|crema de|cremas de|caldo|pure|gazpacho|salmorejo|vichyssoise|consome)/iu', $text);
        $isSauce = (bool) preg_match('/(salsa|mojo|pate|hummus|alioli|mayonesa|vinagreta|aderezo|pesto|romesco|salmoriglio|chutney|compota)/iu', $text);
        $isDrink = (bool) preg_match('/(batido|smoothie|zumo|licuado|coctel|cocktail|milkshake|granizado|horchata|agua fresca)/iu', $text);
        $isDessert = $isBakery || (bool) preg_match('/(postre|dulce|mousse|flan|natilla|helado|compota|trufa|coquito|cajuzinho|bombon|budin|budines|torta|tortas|pestino|pestinos|torrija|torrijas|rosquilla|rosquillas|strudel|crumble|kuchen|bizcocho|galleta|churro|bunuelo|mantecado|polvoron|turron|roscon|ensaimada|sobao)/iu', $text);

        if ($isBakery) $per = 100;
        elseif ($isDessert) $per = 120;
        elseif ($isSoup) $per = 200;
        elseif ($isSauce) $per = 50;
        elseif ($isDrink) $per = 250;
        else $per = 250;

        $servings = (int) round($totalGrams / $per);
        return min(max($servings, 1), 12);
    }

    public function findExplicitServings(string $text): ?int
    {
        if (preg_match('/(\d+)\s*comensales/iu', $text, $sm)) return max(1, (int) $sm[1]);
        if (preg_match('/(\d+)\s*personas/iu', $text, $sm)) return max(1, (int) $sm[1]);
        if (preg_match('/(\d+)\s*raciones/iu', $text, $sm)) return max(1, (int) $sm[1]);
        return null;
    }

    public function isStepStart(string $line): bool
    {
        return (bool) preg_match('/^(?:' . self::SECTION_HEADERS . ')/iu', $line);
    }

    public function isActionLine(string $line): bool
    {
        if (!preg_match('/^\s*' . self::NUM . '/u', $line)) {
            return (bool) preg_match('/\b(cocinar|hornear|freir|hervir|calentar|dejar|reposar|reservar|servir|refrigerar|enfriar|espolvorear|decorar|acompanar|precalentar)\b/iu', $line);
        }
        return false;
    }

    public function matchIngredient(string $line, array $ingredientForms): ?Ingredient
    {
        $matched = null; $bestLen = 0; $matchedPos = PHP_INT_MAX;
        $nonOilEarliest = null; $nonOilEarliestPos = PHP_INT_MAX;
        foreach ($ingredientForms as [$ing, $forms]) {
            foreach ($forms as $f) {
                if (strlen($f) < 3) continue;
                $pattern = '/\b' . preg_quote($f, '/') . '\b/i';
                if (!preg_match($pattern, $line, $m, PREG_OFFSET_CAPTURE)) continue;
                $pos = $m[0][1];
                if (strlen($f) > $bestLen) {
                    $bestLen = strlen($f); $matched = $ing; $matchedPos = $pos;
                }
                if ($ing->fat_per_gram <= 0.7 && $pos < $nonOilEarliestPos) {
                    $nonOilEarliest = $ing; $nonOilEarliestPos = $pos;
                }
                break;
            }
        }
        if ($matched && $matched->fat_per_gram > 0.7 && $nonOilEarliest && $nonOilEarliestPos < $matchedPos) {
            $matched = $nonOilEarliest;
        }
        return $matched;
    }

    public function resolveGrams(string $line, int $grams, ?Ingredient $matched): int
    {
        if ($grams === 0 && $matched) {
            if (stripos($line, 'aceite') !== false) $grams = 15;
            elseif (stripos($line, 'sal') !== false) $grams = 2;
            elseif (stripos($line, 'pimienta') !== false) $grams = 1;
            elseif (stripos($line, 'azucar') !== false) $grams = 5;
            elseif (stripos($line, 'especia') !== false || stripos($line, 'perejil') !== false
                || stripos($line, 'oregano') !== false || stripos($line, 'tomillo') !== false
                || stripos($line, 'romero') !== false || stripos($line, 'laurel') !== false) $grams = 1;
            elseif (stripos($line, 'agua') !== false) $grams = 0;
            elseif (stripos($line, 'vinagre') !== false) $grams = 5;
            elseif (stripos($line, 'limon') !== false) $grams = 10;
            else $grams = 20;
        }

        if ($grams === 0 || $grams > 5000) return 0;

        if ($matched && preg_match('/\bcaldo\b/iu', $line) && preg_match('/pescado|fumet/iu', $line)) return 0;

        if ($matched && $matched->fat_per_gram > 0.7 && $grams > 100) {
            if (preg_match('/\bvaso(s)?\b/iu', $line)) $grams = 50;
            elseif (preg_match('/\btaza(s)?\b/iu', $line)) $grams = 100;
            elseif (preg_match('/\bcopa(s)?\b/iu', $line)) $grams = 50;
            elseif (preg_match('/\blitros?\b|\bkg\b|\bkilos?\b|\bbotella(s)?\b/iu', $line)) $grams = 100;
        }

        return $grams;
    }

    public function extractIngredientName(string $line): string
    {
        $name = trim($line);
        $name = preg_replace('/^\s*' . self::NUM . '\s*/u', '', $name);

        $unitPattern = $this->unitPattern();
        if (preg_match('/^\s*(?:' . $unitPattern . ')\b/iu', $name)) {
            $candidate = preg_replace('/^\s*(?:' . $unitPattern . ')\b\s*(?:de\s+)?/iu', '', $name);
            if ($candidate !== '') $name = $candidate;
        }

        $name = preg_replace('/(\s*,\s*|\s+)(al gusto|opcional|picad[oa]s?|rallad[oa]s?|trocead[oa]s?|fresco|fresca|seco|seca|en rodajas?|en dados|en tiras|en cubos|al momento|y opcional)\s*$/iu', '', $name);
        $name = preg_replace('/^de\s+/iu', '', $name);
        $name = trim($name, " \t\n.,;:()-");

        return $name;
    }

    public function extractSteps(string $description): array
    {
        $text = $description ?? '';
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $body = $text;
        if (preg_match('/(?:preparaci[oó]n|elaboraci[oó]n|pasos|como hacer|como se hace|manera de hacer)\s*:?\s*\n?\s*(.+)$/siu', $text, $m)) {
            $body = $m[1];
        }

        $body = preg_replace('/\.\s*-\s*/u', "\n", $body);
        $body = preg_replace('/\b(?:paso\s+\d+|pasos?)\s*:?\s*/iu', "\n", $body);
        $lines = preg_split('/\s*\n\s*/', trim($body));
        $steps = [];
        $seen = [];
        $guard = 0;
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^[\d\s\-.•*#]+/u', '', $line));
            $line = preg_replace('/\s+/u', ' ', $line);
            $lower = mb_strtolower($line);
            if ($line === '' || mb_strlen($line) < 8) continue;
            if (preg_match('/^(?:ingredientes?|preparaci[oó]n|elaboraci[oó]n|nutrici[oó]n|pasos?)\s*:?\s*$/iu', $lower)) continue;
            if (isset($seen[$lower])) continue;
            $seen[$lower] = true;
            $steps[] = $line;
            if (++$guard >= 40) break;
        }
        return $steps;
    }

    private function unitPattern(): string
    {
        return implode('|', array_map(fn($u) => '(?:' . $u . ')', array_keys(self::UNIT_GRAMS)));
    }

    private function countKnownIngredients(string $part, array $names): int
    {
        $count = 0;
        foreach ($names as $name) {
            if ($name !== '' && strlen($name) >= 3 && stripos($part, $name) !== false) $count++;
        }
        return $count;
    }

    private function splitBareIngredients(string $part, array $names): array
    {
        $names = array_values(array_unique(array_filter($names, fn($n) => strlen($n) >= 3)));
        usort($names, fn($a, $b) => strlen($b) <=> strlen($a));

        $result = [];
        $rest = $part;
        $guard = 0;
        while (strlen($rest) > 20 && $guard++ < 10) {
            $best = null; $bestPos = -1;
            foreach ($names as $name) {
                $pos = stripos($rest, $name);
                if ($pos !== false) {
                    $best = $name; $bestPos = $pos;
                    break;
                }
            }
            if ($best === null) break;
            $prefix = trim(substr($rest, 0, $bestPos));
            if ($prefix !== '' && strlen($prefix) <= 60) $result[] = $prefix;
            $result[] = $best;
            $rest = trim(substr($rest, $bestPos + strlen($best)));
        }
        if (trim($rest) !== '' && strlen(trim($rest)) <= 60) $result[] = trim($rest);
        return array_values(array_filter($result, fn($r) => strlen($r) >= 3));
    }
}
