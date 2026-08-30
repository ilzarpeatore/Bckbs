<?php

namespace App\Services\ProgramsImport\Adapters;

/**
 * Adaptador de rutinas públicas de wger (https://wger.de).
 *
 * Consume el JSON del endpoint `GET /api/v2/routine/{id}/structure/`, cuya
 * jerarquía es: Routine → days → slots → entries (slot_entries) con configs
 * de progresión por iteración (weight/repetitions/sets/rir/rest configs).
 *
 * - Iteración ≈ semana (cuando fit_in_week hace que el ciclo de días dure 1
 *   semana). Los valores por semana se resuelven aplicando los configs:
 *   cada registro tiene {iteration, value, operation: r|+|-, step: abs|percent,
 *   repeat} — "r" reemplaza, "+"/"-" suman/restan en absoluto o porcentaje,
 *   "repeat" mantiene la regla activa en iteraciones posteriores.
 * - Slots con varias entries = superserie → un bloque con varios ejercicios.
 * - Días con is_rest=true → día de descanso (sin plantilla).
 *
 * Si el JSON no trae configs (forma simplificada), usa los valores escalares
 * de cada entry (weight, repetitions, sets, rir, rest) y num_weeks=1.
 */
final class WgerAdapter implements AdapterInterface
{
    public function __construct(private readonly int $targetWeeks = 12)
    {
    }

    public function convert(string $filePath): array
    {
        $json = $this->readJson($filePath);
        if ($json === null) {
            throw new \RuntimeException("JSON de wger inválido o vacío: {$filePath}");
        }

        // el endpoint structure puede devolver el objeto routine o {routine: {...}}
        $routine = is_array(FlexArray::get($json, ['routine'], null)) ? $json['routine'] : $json;
        $days = (array) (FlexArray::get($routine, ['days'], []) ?? []);
        usort($days, fn ($a, $b) => ((int) (FlexArray::get($a, ['order'], 0))) <=> ((int) (FlexArray::get($b, ['order'], 0))));

        // num_weeks: si hay configs con repeat (progresión abierta) usamos el
        // objetivo del comando; si no, la máxima iteración explícita (o 1).
        $maxIteration = $this->maxIteration($days);
        $hasRepeat = $this->hasRepeatConfig($days);
        $numWeeks = $hasRepeat ? max(1, $this->targetWeeks) : max(1, $maxIteration);

        $weeks = [];
        for ($w = 1; $w <= $numWeeks; $w++) {
            $weekDays = [];
            foreach ($days as $i => $day) {
                if (!is_array($day)) {
                    continue;
                }
                $order = (int) (FlexArray::get($day, ['order'], $i + 1));
                $isRest = FlexArray::bool($day, ['is_rest'], false);
                $comment = FlexArray::string($day, ['comment', 'name', 'title'], null);

                if ($isRest) {
                    $weekDays[] = [
                        'day_of_week' => $order,
                        'title'       => $comment !== null && $comment !== '' ? $comment : 'Descanso',
                        'description' => null,
                        'is_rest'     => true,
                        'blocks'      => [],
                    ];
                    continue;
                }

                $blocks = [];
                $slots = (array) (FlexArray::get($day, ['slots'], []) ?? []);
                usort($slots, fn ($a, $b) => ((int) (FlexArray::get($a, ['order'], 0))) <=> ((int) (FlexArray::get($b, ['order'], 0))));

                foreach ($slots as $slot) {
                    if (!is_array($slot)) {
                        continue;
                    }
                    $entries = (array) (FlexArray::get($slot, ['entries', 'slot_entries', 'slot_entries_full'], []) ?? []);
                    $exercises = [];
                    foreach ($entries as $entry) {
                        if (!is_array($entry)) {
                            continue;
                        }
                        $exercises[] = $this->convertEntry($entry, $w);
                    }
                    $exercises = array_values(array_filter($exercises, fn ($e) => $e !== null));
                    if ($exercises !== []) {
                        $blocks[] = [
                            'title'        => count($exercises) > 1 ? 'Superserie' : 'Parte principal',
                            'instructions' => null,
                            'exercises'    => $exercises,
                        ];
                    }
                }

                $weekDays[] = [
                    'day_of_week' => $order,
                    'title'       => $comment !== null && $comment !== '' ? $comment : "Día {$order}",
                    'description' => null,
                    'is_rest'     => false,
                    'blocks'      => $blocks,
                ];
            }

            $weeks[] = [
                'week_number' => $w,
                'title'       => "Semana {$w}",
                'days'        => $weekDays,
            ];
        }

        return [
            'programs' => [
                [
                    'source'      => 'wger',
                    'source_id'   => (string) (FlexArray::get($routine, ['id'], 'wger-' . md5($filePath))),
                    'title'       => (string) (FlexArray::get($routine, ['name', 'title'], 'Rutina wger (importada)')),
                    'description' => (string) (FlexArray::get($routine, ['comment', 'description'], '') ?? ''),
                    'num_weeks'   => $numWeeks,
                    'weeks'       => $weeks,
                ],
            ],
        ];
    }

    /** Convierte una slot_entry de wger en ejercicio canónico para la iteración $week. */
    private function convertEntry(array $entry, int $week): ?array
    {
        $exerciseObj = FlexArray::get($entry, ['exercise_object', 'exercise_data'], null);
        $name = null;
        if (is_array($exerciseObj)) {
            $name = FlexArray::string($exerciseObj, ['name', 'name_en', 'name_de'], null);
        }
        $name ??= FlexArray::string($entry, ['exercise_name', 'name'], null);
        if ($name === null || $name === '') {
            $name = 'Ejercicio wger #' . (FlexArray::get($entry, ['exercise'], '?'));
        }

        $reps = $this->resolveConfig($entry, ['repetitions_config', 'reps_config'], $week);
        if ($reps === null) {
            $reps = FlexArray::get($entry, ['repetitions', 'reps'], null);
        }
        [$repsMin, $repsMax] = FlexArray::repsRange($reps);

        $sets = $this->resolveConfig($entry, ['sets_config'], $week);
        $sets ??= FlexArray::int($entry, ['sets'], null);

        $weight = $this->resolveConfig($entry, ['weight_config'], $week);
        $weight ??= FlexArray::float($entry, ['weight'], null);

        $rir = $this->resolveConfig($entry, ['rir_config'], $week);
        $rir ??= FlexArray::float($entry, ['rir'], null);

        $rpe = $this->resolveConfig($entry, ['rpe_config'], $week);
        $rpe ??= FlexArray::float($entry, ['rpe'], null);

        $rest = $this->resolveConfig($entry, ['rest_config', 'rests_config'], $week);
        $rest ??= FlexArray::get($entry, ['rest', 'rests'], null);
        $restSec = FlexArray::seconds($rest, null);

        return [
            'name'               => $name,
            'source_exercise_id' => FlexArray::get($entry, ['exercise'], null),
            'equipment'          => is_array($exerciseObj) ? FlexArray::get($exerciseObj, ['equipment'], null) : null,
            'muscles'            => is_array($exerciseObj) ? (array) (FlexArray::get($exerciseObj, ['muscles', 'primary_muscles'], []) ?? []) : [],
            'sets'               => (int) ($sets ?? 1),
            'reps_min'           => $repsMin,
            'reps_max'           => $repsMax,
            'rest_sec'           => $restSec,
            'rpe_min'            => $rpe !== null ? (float) $rpe : null,
            'rpe_max'            => $rpe !== null ? (float) $rpe : null,
            'rir_min'            => $rir !== null ? (float) $rir : null,
            'rir_max'            => $rir !== null ? (float) $rir : null,
            'tempo'              => null,
            'load_kg'            => $weight !== null ? (float) $weight : null,
            'weight_percent'     => null,
            'notes'              => FlexArray::string($entry, ['comment', 'notes'], null),
        ];
    }

    /**
     * Resuelve el valor de un config en la iteración $iteration.
     * Formatos soportados:
     *  - null o escalar → ese valor
     *  - ["records" => [{iteration, value, operation, step, repeat}...]] →
     *    se aplican en orden por iteración: "r" fija el valor base desde su
     *    iteración en adelante; "+"/"-" ajustan (abs o percent) en su iteración
     *    y, si repeat=true, en TODAS las posteriores (progresión lineal).
     *  - ["value" => X] → X
     */
    private function resolveConfig(array $entry, array $keys, int $iteration): float|int|string|null
    {
        $config = FlexArray::get($entry, $keys, null);
        if ($config === null) {
            return null;
        }
        if (!is_array($config)) {
            return $config;
        }
        $records = FlexArray::get($config, ['records'], null);
        if (!is_array($records)) {
            $value = FlexArray::get($config, ['value'], null);

            return $value;
        }

        usort($records, fn ($a, $b) => ((int) (FlexArray::get((array) $a, ['iteration'], 1))) <=> ((int) (FlexArray::get((array) $b, ['iteration'], 1))));

        $current = null;
        foreach ($records as $rec) {
            $rec = (array) $rec;
            $recIteration = (int) (FlexArray::get($rec, ['iteration'], 1));
            if ($recIteration > $iteration) {
                break;
            }
            $value = (float) (FlexArray::get($rec, ['value'], 0));
            $operation = (string) (FlexArray::get($rec, ['operation'], 'r'));
            $step = (string) (FlexArray::get($rec, ['step'], 'abs'));
            $repeat = FlexArray::bool($rec, ['repeat'], false);

            if ($operation === 'r') {
                $current = $value;
                continue;
            }

            // +/- : una vez en su iteración; si repeat, también cada iteración posterior
            $times = $repeat ? ($iteration - $recIteration + 1) : 1;
            for ($i = 0; $i < $times; $i++) {
                if ($current === null) {
                    break;
                }
                $delta = $step === 'percent' ? ($current * $value / 100) : $value;
                $current += $operation === '-' ? -$delta : $delta;
            }
        }

        return $current;
    }

    private function hasRepeatConfig(array $days): bool
    {
        foreach ($days as $day) {
            if (!is_array($day)) {
                continue;
            }
            foreach ((array) (FlexArray::get($day, ['slots'], []) ?? []) as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                foreach ((array) (FlexArray::get($slot, ['entries', 'slot_entries', 'slot_entries_full'], []) ?? []) as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    foreach (['weight_config', 'repetitions_config', 'reps_config', 'sets_config', 'rir_config', 'rpe_config', 'rest_config'] as $key) {
                        $cfg = FlexArray::get($entry, [$key], null);
                        if (!is_array($cfg)) {
                            continue;
                        }
                        foreach ((array) (FlexArray::get($cfg, ['records'], []) ?? []) as $rec) {
                            if (FlexArray::bool((array) $rec, ['repeat'], false)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    private function maxIteration(array $days): int
    {
        $max = 1;
        foreach ($days as $day) {
            if (!is_array($day)) {
                continue;
            }
            foreach ((array) (FlexArray::get($day, ['slots'], []) ?? []) as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                foreach ((array) (FlexArray::get($slot, ['entries', 'slot_entries', 'slot_entries_full'], []) ?? []) as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    foreach (['weight_config', 'repetitions_config', 'reps_config', 'sets_config', 'rir_config', 'rpe_config', 'rest_config'] as $key) {
                        $cfg = FlexArray::get($entry, [$key], null);
                        if (!is_array($cfg)) {
                            continue;
                        }
                        $records = FlexArray::get($cfg, ['records'], null);
                        if (!is_array($records)) {
                            continue;
                        }
                        foreach ($records as $rec) {
                            $it = (int) (FlexArray::get((array) $rec, ['iteration'], 1));
                            $max = max($max, $it);
                        }
                    }
                }
            }
        }

        return $max;
    }

    private function readJson(string $filePath): ?array
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException("El archivo no existe: {$filePath}");
        }
        $data = json_decode((string) file_get_contents($filePath), true);

        return is_array($data) ? $data : null;
    }
}
