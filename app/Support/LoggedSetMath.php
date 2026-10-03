<?php

namespace App\Support;

/**
 * Lectura de una serie registrada (`client_exercise_logs.logged_sets[i]`)
 * teniendo en cuenta las técnicas de intensidad (2026-10-03).
 *
 * Problema que resuelve: la ficha de varias técnicas pedía apuntar en UNA
 * serie el total de repeticiones con el peso inicial (rest-pause 80 kg
 * 8+3+2 => 80 × 13). Epley sobre eso da ~115 kg en vez de ~101 kg: récords
 * max_1rm falsos, logros MEJORA_E1RM falsos y e1rm_delta del motor inflado.
 *
 * Formato nuevo de una serie con técnica (app desde 2026-10-03):
 *   carga, reps   => SOLO el primer tramo (lo que cuenta para 1RM/récords)
 *   partes        => [{carga, reps}, ...] tramos extra (bajadas, mini-series)
 *   tecnica       => slug del catálogo (TrainingTechniques)
 *
 * Series antiguas (sin `partes`) con técnica de "total de repeticiones" se
 * consideran infladas: cuentan para volumen y peso máximo, nunca para 1RM
 * ni récord de repeticiones.
 */
class LoggedSetMath
{
    /** Técnicas cuya ficha pedía apuntar el total de repeticiones en una sola serie. */
    public const TOTAL_REPS_TECHNIQUES = [
        'cluster_sets',
        'rest_pause',
        'rest_pause_ampliado',
        'drop_sets',
        'drop_sets_mecanicos',
        'myo_reps',
        // 'parciales' no: su ficha ya pedía apuntar solo las repeticiones completas.
    ];

    public const MAX_PARTS = 6;

    public static function weight(array $set): ?float
    {
        $v = $set['carga'] ?? null;

        return is_numeric($v) ? (float) $v : null;
    }

    public static function reps(array $set): ?int
    {
        $v = $set['reps'] ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    /** @return array<int, array{carga: float, reps: int}> */
    public static function parts(array $set): array
    {
        $out = [];
        foreach ((array) ($set['partes'] ?? []) as $p) {
            if (!is_array($p) || !is_numeric($p['reps'] ?? null) || (int) $p['reps'] <= 0) {
                continue;
            }
            $out[] = ['carga' => is_numeric($p['carga'] ?? null) ? (float) $p['carga'] : 0.0, 'reps' => (int) $p['reps']];
        }

        return $out;
    }

    /**
     * Serie antigua registrada como "total de repeticiones": sus reps no
     * sirven para estimar 1RM ni para récords de repeticiones.
     */
    public static function isInflated(array $set): bool
    {
        return in_array($set['tecnica'] ?? null, self::TOTAL_REPS_TECHNIQUES, true)
            && !array_key_exists('partes', $set);
    }

    /** Reps válidas para 1RM / récord de reps, o null si la serie no cuenta. */
    public static function strengthReps(array $set): ?int
    {
        return self::isInflated($set) ? null : self::reps($set);
    }

    /** Tonelaje de la serie: primer tramo + todas las partes. */
    public static function volume(array $set): float
    {
        $w = self::weight($set);
        $r = self::reps($set);
        $total = ($w !== null && $r !== null && $w > 0 && $r > 0) ? $w * $r : 0.0;
        foreach (self::parts($set) as $p) {
            $total += $p['carga'] * $p['reps'];
        }

        return $total;
    }

    /**
     * Marca con `tecnica` las series que no la traen, a partir del prescrito
     * del ejercicio (todas las series, o solo la última si tecnica_series =
     * "ultima"). Lo usa logSets() para versiones viejas de la app y el
     * recálculo de récords para el histórico.
     */
    public static function annotate(array $sets, array $prescribed): array
    {
        $tecnica = trim((string) ($prescribed['tecnica'] ?? ''));
        if ($tecnica === '' || $tecnica === TrainingTechniques::OTHER) {
            return $sets;
        }
        $lastOnly = ($prescribed['tecnica_series'] ?? null) === TrainingTechniques::SERIES_LAST;
        $last = count($sets) - 1;
        foreach ($sets as $i => $set) {
            if (!is_array($set) || array_key_exists('tecnica', $set)) {
                continue;
            }
            if (!$lastOnly || $i === $last) {
                $sets[$i]['tecnica'] = $tecnica;
            }
        }

        return $sets;
    }

    /**
     * Limpia `partes` y `tecnica` recibidos de la app (logSets): partes
     * numéricas, como mucho MAX_PARTS, y técnica solo del catálogo.
     */
    public static function sanitizeExtras(array $raw): array
    {
        $out = [];
        $tecnica = $raw['tecnica'] ?? null;
        if (is_string($tecnica) && array_key_exists($tecnica, TrainingTechniques::CATALOG)) {
            $out['tecnica'] = $tecnica;
        }
        if (array_key_exists('partes', $raw) && is_array($raw['partes'])) {
            $parts = [];
            foreach (array_slice($raw['partes'], 0, self::MAX_PARTS) as $p) {
                if (!is_array($p) || !is_numeric($p['reps'] ?? null) || (int) $p['reps'] <= 0) {
                    continue;
                }
                $parts[] = [
                    'carga' => is_numeric($p['carga'] ?? null) ? round((float) $p['carga'], 2) : null,
                    'reps'  => (int) $p['reps'],
                ];
            }
            // Una serie con técnica y sin tramos extra sigue siendo "nueva"
            // (partes: []): sus reps son del primer tramo y sí cuentan.
            $out['partes'] = $parts;
        }

        return $out;
    }
}
