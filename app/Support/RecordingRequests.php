<?php

namespace App\Support;

/**
 * «Pedir grabación» (pedido 2026-10-03): el coach marca un ejercicio para
 * que el cliente se grabe haciéndolo. Mismo sitio que la técnica especial,
 * el `prescribed` de cada ejercicio de cada semana (sin migración):
 *
 *   grabar        => true (si no se pide, la clave no está)
 *   grabar_series => "todas" (por defecto) | "primera" | "ultima"
 *   grabar_nota   => texto libre opcional («de lado, que se vea la cadera»)
 *
 * En los overrides de un cliente, quitarla deja `grabar => false` para tapar
 * la de la plantilla (ver apply()). La app marca la serie grabada con
 * `grabado: true` en logged_sets (ClientCalendarController::storeLoggedSets).
 */
class RecordingRequests
{
    public const SERIES_ALL = 'todas';
    public const SERIES_FIRST = 'primera';
    public const SERIES_LAST = 'ultima';
    public const SERIES = [self::SERIES_ALL, self::SERIES_FIRST, self::SERIES_LAST];

    public const NOTE_MAX = 200;

    public const KEYS = ['grabar', 'grabar_series', 'grabar_nota'];

    /** Reglas de validación de los endpoints que guardan la técnica. */
    public static function rules(): array
    {
        return [
            'grabar'        => 'nullable|boolean',
            'grabar_series' => 'nullable|in:'.implode(',', self::SERIES),
            'grabar_nota'   => 'nullable|string|max:'.self::NOTE_MAX,
        ];
    }

    /** true, 1, "1", "true", "sí", "x"... => true; vacío, "no", "0", "false" => false. */
    public static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            return false;
        }
        $norm = self::normalize((string) $value);

        return $norm !== '' && !in_array($norm, ['0', 'no', 'false', 'falso', 'n'], true);
    }

    /** "primera", "1ª", "first" => primera; "última", "last" => ultima; resto => todas. */
    public static function resolveSeries(?string $value): string
    {
        $norm = self::normalize((string) $value);
        if (str_starts_with($norm, 'primer') || $norm === 'first' || $norm === '1a') {
            return self::SERIES_FIRST;
        }
        if (str_starts_with($norm, 'ultim') || $norm === 'last') {
            return self::SERIES_LAST;
        }

        return self::SERIES_ALL;
    }

    /**
     * Pone (o quita) la petición de grabación de un prescrito. Con
     * $maskInherited, quitarla deja `grabar => false` en vez de borrar la
     * clave (overrides de un cliente que se fusionan sobre la plantilla).
     */
    public static function apply(array $prescribed, mixed $grabar, ?string $series, ?string $nota, bool $maskInherited = false): array
    {
        foreach (self::KEYS as $k) {
            unset($prescribed[$k]);
        }
        if (!self::truthy($grabar)) {
            if ($maskInherited) {
                $prescribed['grabar'] = false;
            }

            return $prescribed;
        }

        return self::normalizePrescribed($prescribed + [
            'grabar'        => true,
            'grabar_series' => $series,
            'grabar_nota'   => $nota,
        ]);
    }

    /**
     * Deja coherentes las claves de grabación de un `prescribed` ya
     * fusionado: sin grabar no quedan grabar_series/grabar_nota; con grabar,
     * `true` real, alcance por defecto "todas" y nota recortada.
     * Un `grabar => false` explícito (máscara de override) se conserva.
     */
    public static function normalizePrescribed(array $prescribed): array
    {
        if (!array_key_exists('grabar', $prescribed) || !self::truthy($prescribed['grabar'])) {
            $keepMask = array_key_exists('grabar', $prescribed) && $prescribed['grabar'] === false;
            unset($prescribed['grabar'], $prescribed['grabar_series'], $prescribed['grabar_nota']);
            if ($keepMask) {
                $prescribed['grabar'] = false;
            }

            return $prescribed;
        }

        $prescribed['grabar'] = true;
        $series = $prescribed['grabar_series'] ?? null;
        $prescribed['grabar_series'] = in_array($series, self::SERIES, true) ? $series : self::resolveSeries(is_scalar($series) ? (string) $series : null);

        $nota = is_scalar($prescribed['grabar_nota'] ?? null) ? trim((string) $prescribed['grabar_nota']) : '';
        if ($nota === '') {
            unset($prescribed['grabar_nota']);
        } else {
            $prescribed['grabar_nota'] = mb_substr($nota, 0, self::NOTE_MAX);
        }

        return $prescribed;
    }

    private static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ª' => 'a']);

        return preg_replace('/[^a-z0-9]+/', '', $s);
    }
}
