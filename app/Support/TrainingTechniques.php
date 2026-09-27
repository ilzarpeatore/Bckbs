<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Técnicas especiales de intensidad (pedido 2026-09-27): se guardan en el
 * `prescribed` de cada ejercicio de cada semana, junto a series/reps/RIR:
 *
 *   tecnica        => slug del catálogo (abajo) u "otra"
 *   tecnica_series => "todas" (por defecto) | "ultima" (solo la última serie)
 *   tecnica_otra   => texto libre, solo con tecnica = "otra"
 *
 * Única fuente del catálogo: el panel y la app lo leen de
 * GET admin/training-technique-list y GET v1/training-technique-list
 * (TrainingTechniqueController), no tienen copia propia.
 */
class TrainingTechniques
{
    public const OTHER = 'otra';
    public const SERIES_ALL = 'todas';
    public const SERIES_LAST = 'ultima';

    public const KEYS = ['tecnica', 'tecnica_series', 'tecnica_otra'];

    /** slug => [label, descripción para el cliente] */
    public const CATALOG = [
        'cluster_sets'        => ['Cluster sets', 'Al llegar a RIR 1, pausa 10-15 s, haz 1-2 repeticiones más y repite el ciclo hasta 3 veces.'],
        'bisets'              => ['Bisets', 'Dos ejercicios seguidos sin descanso entre ellos; descansa al terminar el par.'],
        'superseries'         => ['Superseries', 'Dos ejercicios de músculos distintos seguidos, sin descanso entre ellos.'],
        'rest_pause'          => ['Rest-pause', 'Llega al fallo, pausa 15-20 s y continúa hasta el fallo otra vez.'],
        'rest_pause_ampliado' => ['Rest-pause ampliado', 'Tres mini-series hasta el fallo con 15 s de pausa entre ellas.'],
        'drop_sets'           => ['Drop sets', 'Llega al fallo, baja la carga un 20-25 % y continúa hasta el fallo.'],
        'drop_sets_mecanicos' => ['Drop sets mecánicos', 'Al llegar al fallo, cambia a una variante más fácil del ejercicio en vez de bajar el peso.'],
        'series_mecanicas'    => ['Series mecánicas', 'Ejercicio A hasta RIR 1 y, sin descanso, un ejercicio B del mismo patrón hasta el fallo.'],
        'bfr'                 => ['BFR (oclusión)', 'Con restricción parcial del flujo sanguíneo: 3 series de 20-30 repeticiones a RPE 7-8.'],
        'myo_reps'            => ['Myo-reps', 'Una serie de activación cerca del fallo y después mini-series de 3-5 repeticiones con 3-5 respiraciones de pausa.'],
        'parciales'           => ['Parciales', 'Al llegar al fallo, sigue con repeticiones parciales en el tramo más fuerte del movimiento.'],
        'excentricas'         => ['Excéntricas lentas', 'Baja el peso de forma controlada, en 3-5 segundos, en cada repetición.'],
        'isometricas'         => ['Isométricas', 'Mantén la posición indicada durante el tiempo marcado.'],
        self::OTHER           => ['Otra', 'Técnica indicada por tu entrenador.'],
    ];

    /** @return array<int, array{key: string, label: string, description: string}> */
    public static function list(): array
    {
        $out = [];
        foreach (self::CATALOG as $key => [$label, $description]) {
            $out[] = ['key' => $key, 'label' => $label, 'description' => $description];
        }

        return $out;
    }

    /**
     * Slug a partir de lo que escribe el coach en el Excel: el slug, la
     * etiqueta ("Rest-pause", "drop sets") o texto libre. Texto que no
     * coincide con ninguna => [otra, texto].
     *
     * @return array{0: string, 1: string|null}|null
     */
    public static function resolve(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $norm = self::normalize($value);
        foreach (self::CATALOG as $key => [$label]) {
            if ($key === self::OTHER) {
                continue;
            }
            if ($norm === self::normalize($key) || $norm === self::normalize($label)) {
                return [$key, null];
            }
        }

        return [self::OTHER, $value];
    }

    /** "última", "ultima serie", "last" => ultima; cualquier otra cosa => todas. */
    public static function resolveSeries(?string $value): string
    {
        $norm = self::normalize((string) $value);

        return str_starts_with($norm, 'ultim') || $norm === 'last' ? self::SERIES_LAST : self::SERIES_ALL;
    }

    /**
     * Deja coherentes las claves de técnica de un `prescribed` ya fusionado:
     * sin técnica no quedan tecnica_series/tecnica_otra; con técnica,
     * tecnica_series por defecto "todas"; "otra" exige texto.
     *
     * @throws ValidationException si el slug no está en el catálogo
     */
    public static function normalizePrescribed(array $prescribed): array
    {
        $tecnica = isset($prescribed['tecnica']) ? trim((string) $prescribed['tecnica']) : '';
        if ($tecnica === '') {
            unset($prescribed['tecnica'], $prescribed['tecnica_series'], $prescribed['tecnica_otra']);

            return $prescribed;
        }
        if (!array_key_exists($tecnica, self::CATALOG)) {
            throw ValidationException::withMessages(['changes' => "Técnica desconocida: {$tecnica}."]);
        }
        $prescribed['tecnica'] = $tecnica;
        $prescribed['tecnica_series'] = ($prescribed['tecnica_series'] ?? null) === self::SERIES_LAST ? self::SERIES_LAST : self::SERIES_ALL;

        $otra = trim((string) ($prescribed['tecnica_otra'] ?? ''));
        if ($tecnica === self::OTHER) {
            if ($otra === '') {
                throw ValidationException::withMessages(['changes' => 'Con la técnica «Otra» hay que escribir cuál es.']);
            }
            $prescribed['tecnica_otra'] = mb_substr($otra, 0, 120);
        } else {
            unset($prescribed['tecnica_otra']);
        }

        return $prescribed;
    }

    private static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return preg_replace('/[^a-z0-9]+/', '', $s);
    }
}
