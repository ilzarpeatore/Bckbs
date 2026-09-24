<?php

namespace App\Services;

use App\Models\ClientExerciseLog;
use App\Models\Exercise;
use App\Models\BodyPart;
use Illuminate\Support\Carbon;

/**
 * Fuente de verdad única del cálculo de volumen por grupo muscular — portado
 * desde src/lib/muscle-volume.ts + muscle-groups.ts del admin panel (que lo
 * calculaba en el navegador, solo para el admin) para que admin, app móvil y
 * el heatmap post-entrenamiento consuman exactamente la misma lógica.
 *
 * Reparto del volumen de cada serie entre los musculos implicados, segun la
 * activacion muscular medida por estudios EMG (%MVIC). El musculo primario
 * recibe el 100% del volumen (multiplicador 1) y los secundarios una fraccion.
 * Los valores iniciales se han basado en:
 *   - Dickie et al. 2017 (pull-ups): biceps ~80-95% MVIC, braquiorradial 90-97%.
 *   - Mayo Clinic suspension rows (PMC7734360): trapecios 43-94%, deltoides
 *     posterior 45-88%, dorsal 12-48%.
 *   - Contreras et al. 2015 (hip thrust vs squat) y revision sistematica del
 *     deadlift (PMC7046193).
 *   - Inara/EMG squat synthesis: cuadriceps 82-88%, gluteo mayor 75%, isquios
 *     42%, gemelos 38%, lumbar 55%.
 */
class MuscleVolumeService
{
    /** @var array<int, array{match: string[], multipliers: array<string, float>}> */
    private const RULES = [
        [
            'match' => ['dorsales', 'dorsal', 'lat', 'lats', 'latissimus'],
            'multipliers' => [
                'Bíceps' => 0.7,
                'Antebrazo' => 0.6,
                'Trapecios' => 0.3,
                'Deltoides posterior' => 0.3,
                'Espalda alta' => 0.4,
                'Lumbar' => 0.2,
            ],
        ],
        [
            'match' => ['espalda alta', 'trapecios', 'trap', 'traps', 'espalda'],
            'multipliers' => [
                'Dorsales' => 0.5,
                'Bíceps' => 0.5,
                'Antebrazo' => 0.35,
                'Deltoides posterior' => 0.5,
                'Trapecios' => 0.8,
                'Lumbar' => 0.25,
            ],
        ],
        [
            'match' => ['hombros', 'hombro', 'deltoides', 'deltoide'],
            'multipliers' => [
                'Tríceps' => 0.5,
                'Trapecios' => 0.3,
                'Pecho' => 0.3,
            ],
        ],
        [
            'match' => ['pecho', 'pectoral', 'pectorales'],
            'multipliers' => [
                'Tríceps' => 0.6,
                'Deltoides anterior' => 0.4,
                'Deltoides lateral' => 0.15,
            ],
        ],
        [
            'match' => ['cuadriceps', 'cuádriceps', 'quadriceps'],
            'multipliers' => [
                'Gluteo mayor' => 0.7,
                'Gluteo medio' => 0.4,
                'Isquiotibiales' => 0.45,
                'Gemelos' => 0.35,
                'Lumbar' => 0.4,
            ],
        ],
        [
            'match' => ['isquiotibiales', 'isquios', 'isquio', 'femoral', 'femorales', 'lumbar', 'lumbares'],
            'multipliers' => [
                'Lumbar' => 0.9,
                'Gluteo mayor' => 0.8,
                'Cuádriceps' => 0.5,
                'Trapecios' => 0.4,
                'Antebrazo' => 0.4,
            ],
        ],
        [
            'match' => ['gluteo mayor', 'gluteos', 'gluteo', 'hip thrust', 'glute'],
            'multipliers' => [
                'Gluteo medio' => 0.4,
                'Gluteo menor' => 0.25,
                'Isquiotibiales' => 0.5,
                'Lumbar' => 0.3,
            ],
        ],
    ];

    /** Grupos musculares canonicos que siempre aparecen, aunque no tengan volumen. */
    public const DEFAULT_MUSCLE_GROUPS = [
        'Pecho', 'Bíceps', 'Tríceps', 'Antebrazo', 'Trapecios', 'Hombros',
        'Deltoides lateral', 'Deltoides frontal', 'Deltoides posterior',
        'Espalda alta', 'Dorsales', 'Lumbar', 'Abdominales', 'Oblicuos',
        'Cuádriceps', 'Isquiotibiales', 'Gluteo', 'Gluteo medio', 'Gluteo mayor',
        'Gluteo menor', 'Gemelos',
    ];

    private static function normalize(string $s): string
    {
        $s = strtolower(trim($s));
        $s = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $s
        );
        return $s;
    }

    /** Devuelve { musculo: multiplicador } para el grupo primario dado. */
    public static function getMuscleSplit(?string $primaryGroup): array
    {
        $base = $primaryGroup ?? '';
        if ($base === '') {
            return [];
        }
        $key = self::normalize($base);
        foreach (self::RULES as $rule) {
            if (!in_array($key, $rule['match'], true)) {
                continue;
            }
            $out = [$base => 1.0];
            foreach ($rule['multipliers'] as $muscle => $mult) {
                if (self::normalize($muscle) !== $key) {
                    $out[$muscle] = $mult;
                }
            }
            return $out;
        }
        return [$base => 1.0];
    }

    /** Reparte el volumen de una serie (peso x reps) entre los musculos implicados. */
    public static function getMuscleVolumeSplit(?string $primaryGroup, float $setVolume): array
    {
        $split = self::getMuscleSplit($primaryGroup);
        $out = [];
        foreach ($split as $muscle => $mult) {
            $out[$muscle] = $setVolume * $mult;
        }
        return $out;
    }

    /**
     * Resuelve el grupo muscular "primario" de un ejercicio: el primer
     * body_part de su bodypart_ids (mismo criterio que primaryBodypart() en
     * el admin panel — el primero de la lista, no una media de todos).
     *
     * @return array<int, string|null> exercise_id => nombre del body_part primario (o null)
     */
    private static function resolvePrimaryGroups(array $exerciseIds): array
    {
        if (empty($exerciseIds)) {
            return [];
        }
        $bodyPartTitles = BodyPart::pluck('title', 'id');
        $exercises = Exercise::whereIn('id', array_unique($exerciseIds))->get(['id', 'bodypart_ids']);

        $result = [];
        foreach ($exercises as $exercise) {
            $firstId = self::firstBodyPartId($exercise->bodypart_ids);
            $result[$exercise->id] = $firstId !== null ? ($bodyPartTitles[$firstId] ?? null) : null;
        }
        return $result;
    }

    /**
     * `bodypart_ids` normalmente decodifica a un array (ej. [1,4]) via el
     * accessor del modelo, pero algunas filas antiguas lo tienen guardado
     * como escalar suelto ("1" en vez de "[1]") — json_decode("1") da el
     * entero 1, no un array. Mismo caso ya manejado defensivamente en el
     * frontend (parseBodypartIds en muscle-groups.ts); replicado aqui.
     */
    public static function firstBodyPartId(mixed $ids): ?int
    {
        if (is_array($ids)) {
            return count($ids) > 0 ? (int) $ids[0] : null;
        }
        if (is_numeric($ids)) {
            return (int) $ids;
        }
        return null;
    }

    /**
     * Calcula volumen por grupo muscular, por fecha, y por fecha+grupo, a
     * partir de una lista de sets planos. Mismo contrato que las 3 useMemo
     * (volumeByMuscle / volumeByDate / volumeByDateAndMuscle) que este
     * servicio reemplaza en UserDetailView.tsx del admin panel.
     *
     * @param array<int, array{exercise_id: int, weight: float|null, reps: int|null, date?: string|null}> $sets
     * @param float|null $bodyweightKg Peso corporal actual del cliente (user_profiles.weight_in_kg).
     *        Se usa como carga cuando un set no trae peso (dominadas, fondos sin lastre, flexiones...) -
     *        convencion estandar de apps de fitness (Strong/Hevy) para que estos ejercicios no
     *        desaparezcan del todo del volumen/distribucion muscular solo por no llevar peso anadido.
     *        Sin este dato esos sets se descartaban (is_numeric(null) === false), lo que dejaba a
     *        "Pecho"/"Hombros"/"Espalda" en 0 para clientes que entrenan sobre todo en calistenia.
     */
    public static function computeVolume(array $sets, bool $multiplierEnabled = true, ?float $bodyweightKg = null): array
    {
        $exerciseIds = array_column($sets, 'exercise_id');
        $primaryGroups = self::resolvePrimaryGroups($exerciseIds);

        $byMuscle = [];
        $seriesByMuscle = [];
        $byDate = [];
        $byDateAndMuscle = [];
        $seriesCount = 0;
        $sessionDates = [];

        foreach ($sets as $set) {
            $weightRaw = $set['weight'] ?? null;
            $reps = $set['reps'] ?? null;
            if (!is_numeric($reps)) {
                // Filas ya guardadas antes de 2026-09-17 (app React Native,
                // workout_session_screen.tsx) podian llevar el objetivo
                // precargado sin editar (ej. "12-15" reps) en vez de un
                // numero real -- el cliente nuevo ya lo evita, pero esto
                // sigue haciendo tolerante el calculo para no perder series
                // ya registradas en BD. Mismo criterio que parseFloat en JS:
                // toma el primer numero del string, descarta si no hay
                // ninguno.
                if (is_string($reps) && preg_match('/-?\d+(\.\d+)?/', $reps, $m)) {
                    $reps = $m[0];
                } else {
                    continue;
                }
            }
            if (is_numeric($weightRaw)) {
                $weight = (float) $weightRaw;
            } elseif ($bodyweightKg !== null && $bodyweightKg > 0) {
                // Set sin peso (ejercicio a peso corporal) - usar el peso
                // corporal del cliente como carga en vez de descartarlo.
                $weight = $bodyweightKg;
            } else {
                continue;
            }
            $tonnage = $weight * (float) $reps;
            $date = isset($set['date']) ? substr((string) $set['date'], 0, 10) : null;
            $seriesCount++;
            if ($date !== null) {
                $sessionDates[$date] = true;
            }

            if ($date !== null) {
                $byDate[$date] = ($byDate[$date] ?? 0) + $tonnage;
            }

            $primary = $primaryGroups[$set['exercise_id']] ?? null;
            if ($primary === null) {
                continue;
            }
            // Series por musculo = recuento de sets cuyo grupo PRIMARIO es ese
            // musculo (sin fraccionar entre secundarios como si hace el
            // volumen EMG) — "series" es un conteo discreto, no se reparte.
            $seriesByMuscle[$primary] = ($seriesByMuscle[$primary] ?? 0) + 1;
            $split = $multiplierEnabled
                ? self::getMuscleVolumeSplit($primary, $tonnage)
                : [$primary => $tonnage];

            foreach ($split as $group => $v) {
                $byMuscle[$group] = ($byMuscle[$group] ?? 0) + $v;
                if ($date !== null) {
                    $byDateAndMuscle[$date] ??= [];
                    $byDateAndMuscle[$date][$group] = ($byDateAndMuscle[$date][$group] ?? 0) + $v;
                }
            }
        }

        // Incluye los grupos sin trabajo (volumen 0): los trabajados primero
        // (por volumen desc), el resto en orden alfabetico — mismo criterio
        // que la version anterior en el frontend del admin.
        $worked = [];
        foreach ($byMuscle as $group => $volume) {
            $worked[] = ['group' => $group, 'volume' => round($volume, 2)];
        }
        usort($worked, fn ($a, $b) => $b['volume'] <=> $a['volume'] ?: strcmp($a['group'], $b['group']));

        $known = array_column($worked, 'group');
        $unworked = [];
        foreach (self::DEFAULT_MUSCLE_GROUPS as $group) {
            if (!in_array($group, $known, true)) {
                $unworked[] = ['group' => $group, 'volume' => 0];
            }
        }
        sort($unworked);

        // Mismo orden que volumeByMuscle (trabajados por volumen desc, luego
        // el resto alfabetico) para que la tabla por musculo de la pantalla
        // "Distribucion del cuerpo" pueda iterar volumeByMuscle y leer las
        // series de aqui por indice de grupo sin tener que re-ordenar.
        $seriesByMuscleOut = [];
        foreach ([...$worked, ...$unworked] as $row) {
            $seriesByMuscleOut[] = ['group' => $row['group'], 'series' => $seriesByMuscle[$row['group']] ?? 0];
        }

        $volumeByDate = [];
        foreach ($byDate as $date => $volume) {
            $volumeByDate[] = ['date' => $date, 'volume' => round($volume, 2)];
        }
        usort($volumeByDate, fn ($a, $b) => strcmp($a['date'], $b['date']));

        $volumeByDateAndMuscle = [];
        foreach ($byDateAndMuscle as $date => $groups) {
            $row = ['date' => $date];
            foreach ($groups as $group => $volume) {
                $row[$group] = round($volume, 2);
            }
            $volumeByDateAndMuscle[] = $row;
        }
        usort($volumeByDateAndMuscle, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return [
            'volumeByMuscle' => [...$worked, ...$unworked],
            'seriesByMuscle' => $seriesByMuscleOut,
            'volumeByDate' => $volumeByDate,
            'volumeByDateAndMuscle' => $volumeByDateAndMuscle,
            'totalVolume' => round(array_sum($byDate), 2),
            'sessionsCount' => count($sessionDates),
            'totalSeries' => $seriesCount,
        ];
    }

    /**
     * Trae los sets reales de un cliente desde client_exercise_logs (sin el
     * limite de 200 filas que tenia el endpoint original de historial — aqui
     * se filtra por fecha en la query, no en memoria) y calcula el volumen.
     *
     * $endDate (Y-m-d, por defecto hoy) fija el final de la ventana de
     * $days — necesario para el selector de dia (ventana de 7 dias
     * terminando en un dia concreto) y el navegador de semana de la pantalla
     * de Estadisticas, que no siempre miran "hasta hoy".
     */
    public static function computeForClient(int $clientId, int $days, bool $multiplierEnabled = true, ?string $endDate = null): array
    {
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now();
        // latestSnapshots: cada serie marcada genera una fila acumulada --
        // sin esto 3 series contaban como 6 (ver ClientExerciseLog).
        $query = ClientExerciseLog::where('client_id', $clientId)->latestSnapshots($clientId)->orderBy('id');
        $query->where('performed_date', '<=', $end->toDateString());
        if ($days > 0) {
            $query->where('performed_date', '>=', $end->copy()->subDays($days - 1)->toDateString());
        }
        $logs = $query->get(['id', 'exercise_id', 'performed_date', 'logged_sets', 'created_at']);

        $sets = [];
        foreach ($logs as $log) {
            $date = optional($log->performed_date)->toDateString() ?? $log->created_at->toDateString();
            foreach (($log->logged_sets ?? []) as $set) {
                $sets[] = [
                    'exercise_id' => $log->exercise_id,
                    'weight' => $set['carga'] ?? null,
                    'reps' => $set['reps'] ?? null,
                    'date' => $date,
                ];
            }
        }

        $bodyweightKg = optional(\App\Models\User::find($clientId)?->userProfile)->weight_in_kg;
        $bodyweightKg = is_numeric($bodyweightKg) ? (float) $bodyweightKg : null;

        return self::computeVolume($sets, $multiplierEnabled, $bodyweightKg);
    }
}
