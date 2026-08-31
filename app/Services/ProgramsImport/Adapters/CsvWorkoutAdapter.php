<?php

namespace App\Services\ProgramsImport\Adapters;

/**
 * Parser genérico de CSV de workouts (Hevy, Strong, JEFIT, etc.).
 *
 * Detecta columnas por nombre de cabecera (mapeo por fuente), agrupa las filas
 * por día de entrenamiento (columna título) y por ejercicio, y agrega las
 * series de cada ejercicio a una única prescripción canónica.
 *
 * El CSV de estas apps exporta UNA FILA POR SERIE; este parser colapsa las
 * series repetidas del mismo ejercicio en "sets", tomando el peso máximo y la
 * mediana de reps de las series "normales".
 */
final class CsvWorkoutAdapter implements AdapterInterface
{
    /** @var array<string, array<string, array>> */
    private const COLUMN_MAP = [
        'hevy' => [
            'title'    => ['title'],
            'exercise' => ['exercise'],
            'set_type' => ['set type'],
            'weight'   => ['weight'],
            'is_kg'    => ['kg?'],
            'reps'     => ['reps'],
            'distance' => ['distance'],
            'is_km'    => ['kilometers?'],
            'time'     => ['time'],
            'is_sec'   => ['seconds?'],
            'duration' => ['duration'],
            'notes'    => ['notes'],
        ],
        'strong' => [
            'date'     => ['date'],
            'title'    => ['workout name'],
            'exercise' => ['exercise name'],
            'set_type' => ['set type'],
            'set_order' => ['set order'],
            'weight'   => ['weight'],
            'reps'     => ['reps'],
            'notes'    => ['notes'],
        ],
        'jefit' => [
            'date'     => ['date'],
            'title'    => ['workout name', 'workout', 'routine name'],
            'exercise' => ['exercise name', 'exercise'],
            'weight'   => ['weight lbs', 'weight kg', 'weight'],
            'reps'     => ['reps'],
            'rest'     => ['rest seconds', 'rest time', 'rest'],
            'notes'    => ['notes'],
        ],
    ];

    private string $source;

    public function __construct(string $source)
    {
        $this->source = $source;
    }

    public function convert(string $filePath): array
    {
        $rows = $this->readCsv($filePath);
        if ($rows === []) {
            return ['programs' => []];
        }

        // normalizar cabecera
        $map = self::COLUMN_MAP[$this->source] ?? self::COLUMN_MAP['hevy'];
        $columns = $this->normalizeHeaders(array_keys($rows[0]), $map);

        $days = []; // normalizedTitle => ['title','exercises'=>[]]
        foreach ($rows as $row) {
            $title = $this->value($row, $columns, 'title');
            if ($title === null || $title === '') {
                continue;
            }
            $exerciseName = $this->value($row, $columns, 'exercise');
            if ($exerciseName === null || $exerciseName === '') {
                continue;
            }
            $setType = mb_strtolower((string) $this->value($row, $columns, 'set_type', 'normal'));
            $isWarmup = in_array($setType, ['warmup', 'calentamiento', 'warm up'], true);
            $isDrop = in_array($setType, ['drop', 'drop set', 'drop_set'], true);
            $isFail = in_array($setType, ['failure', 'fallo'], true);

            $weight = FlexArray::float($row, [$columns['weight'] ?? 'weight'], null);
            $weight = $this->normalizeWeight($weight, $columns, $row, $this->value($row, $columns, 'is_kg'));
            $reps = FlexArray::int($row, [$columns['reps'] ?? 'reps'], null);
            $duration = FlexArray::seconds($this->value($row, $columns, 'duration'), null);
            $rest = FlexArray::seconds($this->value($row, $columns, 'rest'), null);

            // agrupar por (día, ejercicio) consecutivo
            $dayKey = mb_strtolower(trim($title));
            if (!isset($days[$dayKey])) {
                $days[$dayKey] = ['title' => $title, 'notes' => null, 'exercises' => [], 'order' => count($days)];
            }

            $exKey = mb_strtolower($this->normalizeExerciseName($exerciseName));
            $exercises = &$days[$dayKey]['exercises'];
            $last = count($exercises) - 1;
            if ($last >= 0 && $exercises[$last]['key'] === $exKey && $exercises[$last]['row_gap'] < 3) {
                $entry = &$exercises[$last];
            } else {
                $exercises[] = [
                    'key'         => $exKey,
                    'name'        => $exerciseName,
                    'sets'        => 0,
                    'weights'     => [],
                    'reps'        => [],
                    'notes'       => [],
                    'rest'        => null,
                    'duration'    => null,
                    'is_duration' => false,
                    'row_gap'     => 0,
                ];
                $entry = &$exercises[count($exercises) - 1];
            }
            unset($last, $exercises);

            $entry['row_gap'] = 0;
            if (!$isWarmup) {
                $entry['sets']++;
            }
            if (!$isWarmup && !$isDrop) {
                if ($weight !== null) {
                    $entry['weights'][] = $weight;
                }
                if ($reps !== null) {
                    $entry['reps'][] = $reps;
                }
            }
            if ($isFail) {
                $entry['notes'][] = 'serie al fallo';
            }
            if ($duration !== null && $duration > 0) {
                $entry['is_duration'] = true;
                $entry['duration'] = max((int) ($entry['duration'] ?? 0), $duration);
            }
            if ($rest !== null && $entry['rest'] === null) {
                $entry['rest'] = $rest;
            }
            $note = $this->value($row, $columns, 'notes');
            if ($note) {
                $entry['notes'][] = $note;
            }
        }

        $week = [
            'week_number' => 1,
            'title'       => 'Semana 1',
            'days'        => [],
        ];

        $dayList = array_values($days);
        usort($dayList, fn ($a, $b) => $a['order'] <=> $b['order']);

        foreach ($dayList as $i => $day) {
            $blocks = [];
            $exercises = [];
            foreach ($day['exercises'] as $ex) {
                if ($ex['sets'] <= 0) {
                    continue;
                }
                $repValues = array_values(array_filter($ex['reps'], fn ($v) => $v !== null && $v !== ''));
                $repsMin = $repValues !== [] ? (int) min($repValues) : null;
                $repsMax = $repValues !== [] ? (int) max($repValues) : null;
                $weights = array_values(array_filter($ex['weights'], fn ($v) => $v !== null && $v > 0));
                $loadKg = $weights !== [] ? round(max($weights), 2) : null;
                $notes = implode(' | ', array_unique(array_filter($ex['notes'], fn ($n) => $n !== null && $n !== '')));

                $exercises[] = [
                    'name'              => $ex['name'],
                    'source_exercise_id'=> null,
                    'equipment'         => null,
                    'muscles'           => [],
                    'sets'              => $ex['sets'],
                    'reps_min'          => $repsMin,
                    'reps_max'          => $repsMax,
                    'rest_sec'          => $ex['rest'],
                    'rpe_min'           => null,
                    'rpe_max'           => null,
                    'rir_min'           => null,
                    'rir_max'           => null,
                    'tempo'             => null,
                    'load_kg'           => $loadKg,
                    'weight_percent'    => null,
                    'notes'             => $notes !== '' ? $notes : null,
                    'duration_sec'      => $ex['is_duration'] ? $ex['duration'] : null,
                ];
            }

            if ($exercises !== []) {
                $blocks[] = [
                    'title'        => 'Parte principal',
                    'instructions' => null,
                    'exercises'    => $exercises,
                ];
            }

            $week['days'][] = [
                'day_of_week' => $i + 1,
                'title'       => $day['title'],
                'description' => $day['notes'],
                'is_rest'     => false,
                'blocks'      => $blocks,
            ];
        }

        return [
            'programs' => [
                [
                    'source'      => $this->source,
                    'source_id'   => $this->source . '-' . md5($filePath),
                    'title'       => $this->programTitle(),
                    'description' => 'Programa importado desde exportación ' . strtoupper($this->source),
                    'num_weeks'   => 1,
                    'weeks'       => [$week],
                ],
            ],
        ];
    }

    private function programTitle(): string
    {
        return match ($this->source) {
            'strong' => 'Programa Strong (importado)',
            'jefit'  => 'Programa JEFIT (importado)',
            default => 'Programa Hevy (importado)',
        };
    }

    private function normalizeHeaders(array $headers, array $map): array
    {
        $normalized = [];
        foreach ($headers as $h) {
            $key = strtolower(trim((string) $h));
            $normalized[$h] = $key;
        }

        $columns = [];
        foreach ($map as $field => $accepted) {
            foreach ($normalized as $raw => $lower) {
                if (in_array($lower, array_map('strtolower', $accepted), true)) {
                    $columns[$field] = $raw;
                    break;
                }
            }
        }

        return $columns;
    }

    private function value(array $row, array $columns, string $field, mixed $default = null): mixed
    {
        $key = $columns[$field] ?? null;
        if ($key === null || !array_key_exists($key, $row)) {
            return $default;
        }
        $v = $row[$key];

        return $v === '' ? $default : $v;
    }

    /** Hevy marca kg con "KG?"; si es lbs, convierte a kg. */
    private function normalizeWeight(?float $weight, array $columns, array $row, mixed $isKg): ?float
    {
        if ($weight === null) {
            return null;
        }
        if (isset($columns['is_kg'])) {
            $kg = $this->value($row, $columns, 'is_kg');
            if ($kg !== null && !in_array(strtolower(trim((string) $kg)), ['true', '1', 'yes', 'y', 'kg'], true)) {
                return round($weight * 0.45359237, 2);
            }
        }

        return round($weight, 2);
    }

    private function normalizeExerciseName(string $name): string
    {
        return strtolower(trim($name));
    }

    private function readCsv(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException("El archivo CSV no existe: {$filePath}");
        }
        $fh = fopen($filePath, 'r');
        if ($fh === false) {
            throw new \RuntimeException("No se pudo abrir el archivo: {$filePath}");
        }

        $rows = [];
        $header = null;
        while (($line = fgetcsv($fh)) !== false) {
            $line = array_map(fn ($v) => $v === null ? '' : trim((string) $v), $line);
            if ($header === null) {
                // primera fila no vacía = cabecera
                if (implode('', $line) === '') {
                    continue;
                }
                $header = $line;
                continue;
            }
            if (implode('', $line) === '') {
                continue;
            }
            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = $line[$i] ?? '';
            }
            $rows[] = $row;
        }
        fclose($fh);

        return $rows;
    }
}
