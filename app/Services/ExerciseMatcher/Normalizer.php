<?php

namespace App\Services\ExerciseMatcher;

/**
 * Normaliza nombres de ejercicios: minúsculas, sin acentos, tokens limpios,
 * sin stopwords, plurales a singular. Todo el matching opera sobre el texto
 * normalizado para ser insensible a mayúsculas/acentos.
 */
final class Normalizer
{
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        // reemplazar guiones/puntos por espacios (no por vacío, para no pegar palabras)
        $text = str_replace(['-', '.', ',', ':', ';', '(', ')', '/', '\\', "'", '"', '`'], ' ', $text);

        // quitar acentos
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
        ]);

        // colapsar espacios y tokens raros (ej. "2x", "%", números sueltos se conservan)
        $tokens = preg_split('/\s+/', $text) ?: [];
        $tokens = array_map('trim', $tokens);
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== ''));

        return implode(' ', $tokens);
    }

    public static function tokens(string $normalizedText): array
    {
        $tokens = preg_split('/\s+/', $normalizedText) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== ''));

        $out = [];
        foreach ($tokens as $tok) {
            if (in_array($tok, self::STOPWORDS(), true)) {
                continue;
            }
            $tok = self::singular($tok);
            if ($tok === '' || ctype_digit($tok)) {
                continue;
            }
            $out[] = $tok;
        }

        return array_values(array_unique($out));
    }

    public static function singular(string $token): string
    {
        $plurals = [
            'manos' => 'mano', 'piernas' => 'pierna', 'brazos' => 'brazo',
            'hombros' => 'hombro', 'rodillas' => 'rodilla', 'pies' => 'pie',
            'tobillos' => 'tobillo', 'munecas' => 'muneca', 'codos' => 'codo',
            'pechos' => 'pecho', 'ojos' => 'ojo', 'mancuernas' => 'mancuerna',
            'dominadas' => 'dominada', 'elevaciones' => 'elevacion',
            'extensiones' => 'extension', 'aperturas' => 'apertura',
            'zancadas' => 'zancada', 'abdominales' => 'abdominal',
            'gluteos' => 'gluteo', 'isquiotibiales' => 'isquio',
            'flexiones' => 'flexion', 'planchas' => 'plancha',
            'talones' => 'talon', 'pantorrillas' => 'pantorrilla',
            'gemelos' => 'gemelo', 'estocadas' => 'estocada',
            'fondos' => 'fondo', 'biceps' => 'biceps', 'triceps' => 'triceps',
            'adductores' => 'adductor', 'aductores' => 'aductor',
            'abductores' => 'abductor', 'cuadriceps' => 'cuadriceps',
            'deltos' => 'deltoide', 'deltoides' => 'deltoide',
            'dorsales' => 'dorsal', 'pesas' => 'pesa', 'barras' => 'barra',
            'poleas' => 'polea', 'bandas' => 'banda', 'maquinas' => 'maquina',
        ];

        return $plurals[$token] ?? $token;
    }

    /** Versión del texto normalizado con cada token en singular. */
    public static function singularize(string $normalized): string
    {
        $tokens = preg_split('/\s+/', $normalized) ?: [];

        return implode(' ', array_map(fn ($t) => self::singular(trim($t)), $tokens));
    }

    private static function STOPWORDS(): array
    {
        return Dictionaries::STOPWORDS;
    }
}
