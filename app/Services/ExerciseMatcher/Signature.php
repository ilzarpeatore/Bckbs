<?php

namespace App\Services\ExerciseMatcher;

/**
 * Firma de un ejercicio: [movement, muscle, equipment].
 * - movement:  movimiento canónico detectado por frase en SIGNALS (o null).
 * - muscle:    grupo muscular canónico (null = desconocido, compat. con todo).
 * - equipment: título de equipamiento como existe en nuestra BD (null = cualquier).
 */
final class Signature
{
    public function __construct(
        public readonly ?string $movement,
        public readonly ?string $muscle,
        public readonly ?string $equipment,
        public readonly array $tokens = [],
        public readonly array $expandedTokens = [],
    ) {
    }

    public function isCompatibleWith(self $other): bool
    {
        // movement: null no puede "matchear fuerte" (se resuelve por muscle/nombre)
        if ($this->movement !== null && $other->movement !== null && $this->movement !== $other->movement) {
            return false;
        }
        if ($this->muscle !== null && $other->muscle !== null && $this->muscle !== $other->muscle) {
            return false;
        }
        if ($this->equipment !== null && $other->equipment !== null && $this->equipment !== $other->equipment) {
            return false;
        }
        return true;
    }

    public static function fromDb(?string $title, ?string $dbEquipment, array $dbMuscles = []): self
    {
        $title = $title ?? '';
        $equipment = $dbEquipment ? self::cleanEquipment($dbEquipment) : null;
        $muscle = null;

        $normalized = Normalizer::normalize($title);

        // movimiento por señal de frase
        $signal = self::detectSignal($normalized);
        $movement = $signal['movement'] ?? null;

        // músculo: primero por body parts de BD (autoritativo), luego por señal
        if ($dbMuscles !== []) {
            $found = null;
            foreach ($dbMuscles as $m) {
                $key = self::muscleKey($m);
                if ($key !== null) {
                    $found = $key;
                    break;
                }
            }
            $muscle = $found ?? $signal['muscle'] ?? null;
        } else {
            $muscle = $signal['muscle'] ?? null;
        }

        return new self(
            $movement,
            $muscle,
            $equipment,
            Normalizer::tokens($normalized),
            self::expandTokens($normalized),
        );
    }

    public static function fromSource(string $title, ?string $sourceEquipment = null, array $sourceMuscles = [], ?string $mappedEquipment = null): self
    {
        $normalized = Normalizer::normalize($title);
        $signal = self::detectSignal($normalized);

        $equipment = null;
        if ($mappedEquipment !== null) {
            $equipment = self::cleanEquipment($mappedEquipment);
        }
        if ($equipment === null && $sourceEquipment !== null) {
            $equipment = self::cleanEquipment($sourceEquipment);
        }
        if ($equipment === null) {
            $equipment = self::detectEquipment($normalized);
        }

        // músculo: metadatos de la fuente primero, luego señal del nombre
        $muscle = null;
        foreach ($sourceMuscles as $m) {
            $key = self::muscleKey($m);
            if ($key !== null) {
                $muscle = $key;
                break;
            }
        }
        $muscle ??= $signal['muscle'] ?? null;

        return new self(
            $signal['movement'] ?? null,
            $muscle,
            $equipment,
            Normalizer::tokens($normalized),
            self::expandTokens($normalized),
        );
    }

    /** Encuentra la frase de SIGNALS más larga contenida en el texto normalizado. */
    public static function detectSignal(string $normalized): array
    {
        // trabajar sobre el texto singularizado para que "gemelo" matchee "gemelos"
        $singular = Normalizer::singularize($normalized);

        $best = null;
        $bestLen = 0;
        foreach (Dictionaries::SIGNALS as $phrase => $info) {
            $len = strlen($phrase);
            if ($len <= $bestLen) {
                continue;
            }
            if (self::containsPhrase($singular, Normalizer::singularize($phrase))) {
                $best = $info;
                $bestLen = $len;
            }
        }

        return $best ?? ['movement' => null, 'muscle' => null];
    }

    private static function containsPhrase(string $normalized, string $phrase): bool
    {
        if ($phrase === '') {
            return false;
        }
        $pos = mb_strpos($normalized, $phrase);
        if ($pos === false) {
            return false;
        }
        $before = $pos === 0 ? '' : substr($normalized, $pos - 1, 1);
        $after = substr($normalized, $pos + strlen($phrase), 1);
        $isBoundary = fn ($ch) => $ch === '' || $ch === ' ';

        return $isBoundary($before) && $isBoundary($after);
    }

    public static function detectEquipment(string $normalized): ?string
    {
        $singular = Normalizer::singularize($normalized);

        $best = null;
        $bestLen = 0;
        foreach (Dictionaries::EQUIPMENT_TERMS as $term => $title) {
            $len = strlen($term);
            if ($len <= $bestLen) {
                continue;
            }
            if (self::containsPhrase($singular, Normalizer::singularize($term))) {
                $best = $title;
                $bestLen = $len;
            }
        }

        return $best;
    }

    public static function muscleKey(string $raw): ?string
    {
        $normalized = Normalizer::normalize($raw);
        $normalized = preg_replace('/[^a-z ]/', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        $key = Dictionaries::MUSCLE_MAP[$normalized] ?? null;
        if ($key !== null) {
            return $key;
        }

        // fallback: subcadena
        foreach (Dictionaries::MUSCLE_MAP as $term => $k) {
            if (strlen($term) < 3) {
                continue;
            }
            if (str_contains($normalized, $term)) {
                return $k;
            }
        }

        return null;
    }

    /** "Barra"|"Barra EZ"|... -> título canónico de la tabla equipment. */
    public static function cleanEquipment(string $raw): ?string
    {
        $normalized = Normalizer::normalize($raw);
        foreach (Dictionaries::EQUIPMENT_TERMS as $term => $title) {
            if ($normalized === $term) {
                return $title;
            }
        }
        foreach (Dictionaries::EQUIPMENT_TERMS as $term => $title) {
            if (strlen($term) < 3) {
                continue;
            }
            if (str_contains($normalized, $term)) {
                return $title;
            }
        }

        return ucfirst($raw);
    }

    /** Expande tokens con sinónimos (ALIASES) para mejorar la similaridad. */
    private static function expandTokens(string $normalized): array
    {
        $tokens = Normalizer::tokens($normalized);
        $singular = Normalizer::singularize($normalized);
        foreach (Dictionaries::ALIASES as $phrase => $targetPhrase) {
            if (self::containsPhrase($singular, Normalizer::singularize($phrase))) {
                foreach (Normalizer::tokens($targetPhrase) as $tok) {
                    $tokens[] = $tok;
                }
            }
        }

        return array_values(array_unique($tokens));
    }
}
