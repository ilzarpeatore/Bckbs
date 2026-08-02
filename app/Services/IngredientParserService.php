<?php

namespace App\Services;

use Illuminate\Support\Str;

class IngredientParserService
{
    private const FRACTIONS = [
        '¼' => 0.25, '½' => 0.5, '¾' => 0.75,
        '⅓' => 0.333, '⅔' => 0.667,
        '⅛' => 0.125, '⅜' => 0.375, '⅝' => 0.625, '⅞' => 0.875,
    ];

    private const SPANISH_UNITS = [
        'kilos' => 'kg', 'kilo' => 'kg', 'kg' => 'kg',
        'gramos' => 'g', 'gramo' => 'g', 'gr' => 'g', 'g' => 'g',
        'onzas' => 'oz', 'onza' => 'oz',
        'libras' => 'lb', 'libra' => 'lb',
        'litros' => 'L', 'litro' => 'L', 'l' => 'L',
        'mililitros' => 'ml', 'mililitro' => 'ml', 'ml' => 'ml',
        'tazas' => 'cup', 'taza' => 'cup',
        'cucharadas' => 'tbsp', 'cucharada' => 'tbsp', 'cdas' => 'tbsp', 'cda' => 'tbsp',
        'cucharaditas' => 'tsp', 'cucharadita' => 'tsp', 'cdtas' => 'tsp', 'cdta' => 'tsp',
        'piezas' => 'pc', 'pieza' => 'pc',
        'unidades' => 'unit', 'unidad' => 'unit',
        'rodajas' => 'slice', 'rodaja' => 'slice',
        'rebanadas' => 'slice', 'rebanada' => 'slice',
        'dientes' => 'cloves', 'diente' => 'cloves',
        'hojas' => 'unit', 'hoja' => 'unit',
        'ramitas' => 'unit', 'ramita' => 'unit',
        'pizca' => 'pinch',
    ];

    private const UNIT_PATTERN = 'kilos|kilo|kg|gramos|gramo|gr|g|onzas|onza|libras|libra|litros|litro|ml|tazas|taza|cucharadas|cucharada|cdas|cda|cucharaditas|cucharadita|cdtas|cdta|piezas|pieza|unidades|unidad|rodajas|rodaja|rebanadas|rebanada|dientes|diente|hojas|hoja|ramitas|ramita|pizca';

    private const STOP_WORDS = [
        'y', 'o', 'con', 'sin', 'para', 'al', 'del', 'de', 'el', 'la', 'los', 'las', 'un', 'una', 'uno', 'unas', 'unos',
        'fresco', 'frescos', 'fresca', 'frescas', 'natural', 'naturales', 'a temperatura ambiente', 'opcional', 'al gusto',
        'al horno', 'a la plancha', 'a la parrilla', 'en trocitos', 'en rodajas', 'en cubos', 'picado', 'picada', 'picados', 'picadas',
        'rallado', 'rallada', 'rallados', 'ralladas', 'cortado', 'cortada', 'cortados', 'cortadas', 'limpio', 'limpia', 'limpios', 'limpias',
        'pelado', 'pelada', 'pelados', 'peladas', 'cocido', 'cocida', 'cocidos', 'cocidas', 'crudo', 'cruda', 'crudos', 'crudas',
        'asado', 'asada', 'asados', 'asadas', 'frito', 'frita', 'fritos', 'fritas', 'guisado', 'guisada', 'guisados', 'guisadas',
        'templado', 'templada', 'templados', 'templadas', 'caliente', 'calientes', 'frío', 'fría', 'fríos', 'frías',
        'molido', 'molida', 'molidos', 'molidas', 'en polvo', 'en rama', 'en hojas', 'en trozos', 'en láminas', 'en cubos',
        'tipo', 'estilo', 'marca', 'de marca', 'casero', 'casera', 'caseros', 'caseras', 'artesanal', 'artesanales',
    ];

    public function parseIngredientsText(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $text = str_replace('\n', "\n", $text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        $lines = $this->splitIntoItems($text);
        $results = [];
        foreach ($lines as $line) {
            $parsed = $this->parseSingleLine($line);
            if ($parsed) {
                $results[] = $parsed;
            }
        }

        return $results;
    }

    private function splitIntoItems(string $text): array
    {
        $text = trim($text);
        if (empty($text)) {
            return [];
        }

        // Best case: newline-separated
        $lines = preg_split('/[\n\r]+/', $text);
        if (count($lines) > 2) {
            return array_filter(array_map('trim', $lines));
        }

        // Good case: comma-separated starting with numbers
        $lines = preg_split('/,\s*(?=\d)/', $text);
        if (count($lines) > 2) {
            return array_filter(array_map('trim', $lines));
        }

        // Worst case: flat string with no delimiters
        return $this->splitFlatString($text);
    }

    private function splitFlatString(string $text): array
    {
        // Strategy: use a regex that captures quantity + unit + name up to the next quantity+unit
        $pattern = '/(\d+[\.,]?\d*)\s*(' . self::UNIT_PATTERN . ')\b\.?\s*(?:de\s+)?(.+?)(?=(?:\d+[\.,]?\d*)\s*(?:' . self::UNIT_PATTERN . ')\b|$)/iu';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            $pieces = [];
            foreach ($matches as $match) {
                $qty = $match[1];
                $unit = $match[2];
                $name = trim($match[3]);
                $name = rtrim($name, ' ,;.');
                $piece = trim("{$qty} {$unit} de {$name}");
                if (mb_strlen($piece) > 2) {
                    $pieces[] = $piece;
                }
            }

            // Conservative post-process: only split the last piece if it contains multiple known ingredients
            // This handles the common case where the last ingredient list runs to the end of the string
            if (count($pieces) > 0) {
                $lastPiece = $pieces[count($pieces) - 1];
                $splits = $this->splitLastPieceByKnownIngredients($lastPiece);
                if (count($splits) > 1) {
                    // Replace the last piece with its splits
                    array_pop($pieces);
                    foreach ($splits as $split) {
                        $pieces[] = $split;
                    }
                }
            }

            if (count($pieces) > 0) {
                return $pieces;
            }
        }

        // Fallback: simple comma split
        $lines = explode(',', $text);
        return array_filter(array_map('trim', $lines));
    }

    private function splitLastPieceByKnownIngredients(string $piece): array
    {
        $knownIngredients = IngredientMappingService::getKnownIngredientNames();
        usort($knownIngredients, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        // Find all known ingredient positions within the piece
        $positions = [];
        foreach ($knownIngredients as $ingredient) {
            $pattern = '/\b' . preg_quote($ingredient, '/') . '\b/iu';
            if (preg_match_all($pattern, $piece, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $positions[] = [
                        'start' => $match[1],
                        'end' => $match[1] + mb_strlen($match[0]),
                        'ingredient' => $match[0],
                    ];
                }
            }
        }

        if (count($positions) <= 1) {
            return [$piece];
        }

        // Sort and remove overlapping
        usort($positions, fn($a, $b) => $a['start'] <=> $b['start']);
        $filtered = [];
        $lastEnd = -1;
        foreach ($positions as $pos) {
            if ($pos['start'] >= $lastEnd) {
                $filtered[] = $pos;
                $lastEnd = $pos['end'];
            }
        }

        // Split the piece into sub-pieces, each starting with its known ingredient
        $splits = [];
        for ($i = 0; $i < count($filtered); $i++) {
            $start = $filtered[$i]['start'];
            $end = ($i + 1 < count($filtered)) ? $filtered[$i + 1]['start'] : strlen($piece);
            $subPiece = trim(substr($piece, $start, $end - $start));
            $subPiece = rtrim($subPiece, ' ,;.');
            if (mb_strlen($subPiece) > 2) {
                $splits[] = $subPiece;
            }
        }

        return $splits;
    }

    private function parseSingleLine(string $line): ?array
    {
        $line = trim($line, " \t\n\r\0\x0B,;.:-");
        if (empty($line) || mb_strlen($line) < 2) {
            return null;
        }

        $line = $this->normalizeFractions($line);

        $quantity = null;
        $unitText = null;
        $name = $line;

        // Pattern: number unit name
        if (preg_match('/^(\d+[\.,]?\d*)\s*(.+)$/u', $line, $m)) {
            $rawNum = str_replace(',', '.', $m[1]);
            $quantity = (float) $rawNum;
            $remainder = trim($m[2]);
            $unitMatch = $this->matchUnit($remainder);

            if ($unitMatch) {
                $unitText = $unitMatch['symbol'];
                $afterUnit = substr($remainder, strlen($unitMatch['raw']));
                $afterUnit = preg_replace('/^\s*(de|del|al|a)\s+/i', '', $afterUnit);
                $name = trim($afterUnit);
            } else {
                $name = $remainder;
            }
        }
        // Pattern: unit number name (e.g., "gr 200 de queso")
        elseif (preg_match('/^([a-záéíóúñ]+)\s+(\d+[\.,]?\d*)\s*(.+)$/iu', $line, $m)) {
            $unitMatch = $this->matchUnit($m[1]);
            if ($unitMatch) {
                $unitText = $unitMatch['symbol'];
                $quantity = (float) str_replace(',', '.', $m[2]);
                $afterPart = trim($m[3]);
                $afterPart = preg_replace('/^\s*(de|del|al|a)\s+/i', '', $afterPart);
                $name = trim($afterPart);
            }
        }

        $name = $this->cleanIngredientName($name);
        if (empty($name) || mb_strlen($name) < 2) {
            return null;
        }

        if ($quantity === null) {
            $quantity = 1;
        }

        return [
            'quantity' => $quantity,
            'unit' => $unitText,
            'name' => $name,
            'raw' => $line,
        ];
    }

    private function matchUnit(string $text): ?array
    {
        $textLower = mb_strtolower(trim($text));

        // Sort by length descending to match longer first
        $sorted = self::SPANISH_UNITS;
        uksort($sorted, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($sorted as $spanish => $symbol) {
            $escaped = preg_quote($spanish, '/');
            $pattern = '/^\s*' . $escaped . '\.?\s*(?:de\s+)?/ui';
            if (preg_match($pattern, $textLower)) {
                return [
                    'symbol' => $symbol,
                    'raw' => $spanish,
                ];
            }
        }

        return null;
    }

    private function normalizeFractions(string $text): string
    {
        foreach (self::FRACTIONS as $char => $value) {
            $text = str_replace($char, ' ' . $value, $text);
        }

        $text = preg_replace_callback('/(\d+)\s*\/\s*(\d+)/', function ($m) {
            return ' ' . round((float)$m[1] / (float)$m[2], 3);
        }, $text);

        return $text;
    }

    private function cleanIngredientName(string $name): string
    {
        $name = trim($name, " \t\n\r\0\x0B,;.:-");
        $name = preg_replace('/\s+/', ' ', $name);

        // Remove "de X" prefix if it starts with one
        $prefixes = ['de ', 'del ', 'al ', 'a ', 'sin ', 'con '];
        foreach ($prefixes as $prefix) {
            if (mb_strtolower(mb_substr($name, 0, strlen($prefix))) === $prefix) {
                $candidate = mb_substr($name, strlen($prefix));
                if (!empty($candidate) && mb_strlen($candidate) > 1) {
                    $name = $candidate;
                }
            }
        }

        // Remove parenthetical content
        $name = preg_replace('/\s*\(.*?\)\s*/', ' ', $name);

        // Remove trailing descriptors with word boundaries
        $name = preg_replace('/\s*,?\s*\b(sin|con|para|o|y)\b\s+.*$/iu', '', $name);

        // Remove leading numbers
        $name = preg_replace('/^\d+[\.,]?\d*\s*/', '', $name);

        // Remove common stop words from the end
        $name = $this->removeTrailingStopWords($name);

        // Trim and clean
        $name = trim($name, " ,;.:-");

        // Capitalize first letter
        $name = mb_strtolower($name);
        $name = ucfirst($name);

        return $name;
    }

    private function removeTrailingStopWords(string $name): string
    {
        $words = explode(' ', $name);
        $validWords = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) {
                continue;
            }
            $validWords[] = $word;
        }

        // Remove trailing stop words one by one
        while (!empty($validWords)) {
            $lastWord = mb_strtolower($validWords[count($validWords) - 1]);
            if (in_array($lastWord, self::STOP_WORDS)) {
                array_pop($validWords);
            } else {
                break;
            }
        }

        return implode(' ', $validWords);
    }

    public function getSpanishUnitMap(): array
    {
        return self::SPANISH_UNITS;
    }
}
