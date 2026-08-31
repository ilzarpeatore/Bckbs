<?php

namespace App\Services\ProgramsImport\Adapters;

/**
 * Acceso defensivo a arrays asociativos: devuelve el primer valor no null
 * entre varias claves candidatas (para tolerar diferencias de formato entre
 * openweight/wger/exportaciones manuales).
 */
final class FlexArray
{
    public static function get(array $arr, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (is_array($arr) && array_key_exists($key, $arr) && $arr[$key] !== null && $arr[$key] !== '') {
                return $arr[$key];
            }
        }

        return $default;
    }

    public static function int(array $arr, array $keys, ?int $default = null): ?int
    {
        $v = self::get($arr, $keys, $default);
        if ($v === null || $v === '') {
            return $default;
        }

        return (int) $v;
    }

    public static function float(array $arr, array $keys, ?float $default = null): ?float
    {
        $v = self::get($arr, $keys, $default);
        if ($v === null || $v === '') {
            return $default;
        }

        return (float) str_replace(',', '.', (string) $v);
    }

    public static function bool(array $arr, array $keys, bool $default = false): bool
    {
        $v = self::get($arr, $keys, $default);
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            return in_array(mb_strtolower(trim($v)), ['true', '1', 'si', 'yes', 'y', 't'], true);
        }

        return (bool) $v;
    }

    public static function string(array $arr, array $keys, ?string $default = null): ?string
    {
        $v = self::get($arr, $keys, $default);
        if ($v === null) {
            return $default;
        }

        return (string) $v;
    }

    /** "02:00" -> 120 segundos. Acepta "60", "02:00", "02:00:00". */
    public static function seconds(string|int|null $value, ?int $default = null): ?int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $s = trim((string) $value);
        if (ctype_digit($s)) {
            return (int) $s;
        }
        $parts = array_map('intval', explode(':', $s));
        if (count($parts) === 2) {
            return ($parts[0] * 60) + $parts[1];
        }
        if (count($parts) === 3) {
            return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        }

        return $default;
    }

    /** "8" -> [8,8]; "8-10" -> [8,10]; "8x3" -> [3? no]; usa - o espacio como rango. */
    public static function repsRange(string|int|null $value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }
        $s = trim((string) $value);
        $s = preg_replace('/[^0-9\-– ]/', '', $s) ?? $s;
        $s = str_replace(['–', '—'], '-', $s);
        $parts = preg_split('/[\s\-]+/', $s) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
        $nums = array_map('intval', $parts);
        $nums = array_values(array_filter($nums, fn ($n) => $n > 0));

        if ($nums === []) {
            return [null, null];
        }

        return [min($nums), max($nums)];
    }
}
