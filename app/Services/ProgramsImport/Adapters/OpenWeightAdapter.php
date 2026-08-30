<?php

namespace App\Services\ProgramsImport\Adapters;

/**
 * Adaptador del estándar JSON de openweight (www.openweight.app), que es el
 * formato que produce `npx @openweight/cli convert hevy.csv -o programa.json`.
 *
 * Soporta:
 *  - Programa openweight: { "name", "notes", "program": {...}, "workout_templates": [...] }
 *  - Plantilla suelta:    { "name", "notes", "sets": [...] }
 *
 * Cada "set" de una plantilla se convierte en un bloque; cada elemento de su
 * "circuit" (puede haber varios = superserie) en un ejercicio.
 */
final class OpenWeightAdapter implements AdapterInterface
{
    public function convert(string $filePath): array
    {
        $json = $this->readJson($filePath);
        if ($json === null) {
            throw new \RuntimeException("JSON inválido o vacío: {$filePath}");
        }

        // si es un Programa (tiene workout_templates) o una plantilla suelta
        $templates = FlexArray::get($json, ['workout_templates', 'templates'], null);

        $programs = [];

        if (is_array($templates)) {
            $weeks = [];
            $days = [];
            foreach ($templates as $i => $tpl) {
                $template = $this->convertTemplate($tpl, $i + 1);
                $weeks = $this->mergeTemplateWeeks($weeks, $template);
                $days[] = $template;
            }
            // semanas explícitas si vienen en el programa, si no: 1 semana con todos los días
            $programWeeks = FlexArray::get($json, ['weeks'], null);
            $canonicalWeeks = [];
            if (is_array($programWeeks) && $programWeeks !== []) {
                foreach ($programWeeks as $pw) {
                    $canonicalWeeks[] = $this->normalizeWeek($pw);
                }
            } elseif ($weeks !== []) {
                $canonicalWeeks = $weeks;
            } else {
                $canonicalWeeks = [[
                    'week_number' => 1,
                    'title'       => 'Semana 1',
                    'days'        => $this->extractDayList($days),
                ]];
            }

            $programs[] = [
                'source'      => 'openweight',
                'source_id'   => (string) (FlexArray::get($json, ['id', 'program_id'], 'program-' . md5($filePath))),
                'title'       => (string) (FlexArray::get($json, ['name', 'title'], 'Programa openweight (importado)')),
                'description' => (string) (FlexArray::get($json, ['notes', 'description'], '') ?? ''),
                'num_weeks'   => count($canonicalWeeks),
                'weeks'       => $canonicalWeeks,
            ];
        } else {
            // plantilla suelta => programa de una semana
            $week = [
                'week_number' => 1,
                'title'       => 'Semana 1',
                'days'        => [$this->convertTemplate($json, 1)],
            ];
            $programs[] = [
                'source'      => 'openweight',
                'source_id'   => (string) (FlexArray::get($json, ['id'], 'ow-' . md5($filePath))),
                'title'       => (string) (FlexArray::get($json, ['name', 'title'], 'Plantilla openweight (importada)')),
                'description' => (string) (FlexArray::get($json, ['notes', 'description'], '') ?? ''),
                'num_weeks'   => 1,
                'weeks'       => [$week],
            ];
        }

        return ['programs' => $programs];
    }

    /** Convierte una plantilla openweight en un día canónico. */
    private function convertTemplate(array $tpl, int $index): array
    {
        $blocks = [];
        $rawSets = FlexArray::get($tpl, ['sets', 'blocks', 'sections'], []);
        if (!is_array($rawSets)) {
            $rawSets = [];
        }

        foreach ($rawSets as $set) {
            if (!is_array($set)) {
                continue;
            }
            $circuit = FlexArray::get($set, ['circuit', 'exercises'], []);
            if (!is_array($circuit)) {
                $circuit = [];
            }
            $exercises = [];
            foreach ($circuit as $ex) {
                if (!is_array($ex)) {
                    continue;
                }
                $name = (string) (FlexArray::get($ex, ['name', 'exercise', 'exercise_name'], ''));
                if ($name === '') {
                    continue;
                }
                $repsRaw = FlexArray::get($ex, ['reps', 'repetitions'], null);
                [$repsMin, $repsMax] = FlexArray::repsRange(is_array($repsRaw) ? null : $repsRaw);

                $exercises[] = [
                    'name'              => $name,
                    'source_exercise_id'=> FlexArray::get($ex, ['id', 'exercise_id'], null),
                    'equipment'         => FlexArray::get($ex, ['equipment'], null),
                    'muscles'           => (array) (FlexArray::get($ex, ['muscles', 'primary_muscles'], []) ?? []),
                    'sets'              => (int) (FlexArray::get($ex, ['sets'], 1) ?? 1),
                    'reps_min'          => $repsMin,
                    'reps_max'          => $repsMax,
                    'rest_sec'          => FlexArray::seconds(FlexArray::get($ex, ['rest_seconds', 'rest_sec', 'rest'], null), null),
                    'rpe_min'           => FlexArray::float($ex, ['rpe_min', 'min_rpe'], null),
                    'rpe_max'           => FlexArray::float($ex, ['rpe_max', 'max_rpe'], null),
                    'rir_min'           => FlexArray::float($ex, ['rir_min', 'min_rir'], null),
                    'rir_max'           => FlexArray::float($ex, ['rir_max', 'max_rir'], null),
                    'tempo'             => FlexArray::string($ex, ['tempo'], null),
                    'load_kg'           => FlexArray::float($ex, ['weight_kg', 'weight', 'load'], null),
                    'weight_percent'    => FlexArray::float($ex, ['percentage', 'weight_percent', 'percent'], null),
                    'notes'             => FlexArray::string($ex, ['notes'], null),
                ];
            }

            if ($exercises !== []) {
                $blocks[] = [
                    'title'        => (string) (FlexArray::get($set, ['title', 'name'], 'Superserie' . ($circuit > 1 ? '' : '')) ?? 'Parte principal'),
                    'instructions' => FlexArray::get($set, ['notes'], null),
                    'exercises'    => $exercises,
                ];
            }
        }

        return [
            'day_of_week' => (int) (FlexArray::get($tpl, ['day_of_week', 'day'], $index)),
            'title'       => (string) (FlexArray::get($tpl, ['name', 'title'], "Día {$index}")),
            'description' => FlexArray::get($tpl, ['notes'], null),
            'is_rest'     => false,
            'blocks'      => $blocks,
        ];
    }

    private function mergeTemplateWeeks(array $weeks, array $day): array
    {
        if ($weeks === []) {
            return [['week_number' => 1, 'title' => 'Semana 1', 'days' => [$day]]];
        }
        $weeks[0]['days'][] = $day;

        return $weeks;
    }

    private function extractDayList(array $days): array
    {
        // ya son días canónicos; ajustar day_of_week secuencial
        foreach ($days as $i => $day) {
            $days[$i]['day_of_week'] = $i + 1;
        }

        return $days;
    }

    private function normalizeWeek(array $week): array
    {
        $days = FlexArray::get($week, ['days'], []);
        $out = [];
        foreach ((array) $days as $i => $day) {
            if (!is_array($day)) {
                continue;
            }
            $out[] = [
                'day_of_week' => (int) (FlexArray::get($day, ['day_of_week', 'day'], $i + 1)),
                'title'       => (string) (FlexArray::get($day, ['title', 'name'], "Día " . ($i + 1))),
                'description' => FlexArray::get($day, ['description', 'notes'], null),
                'is_rest'     => FlexArray::bool($day, ['is_rest', 'rest'], false),
                'blocks'      => $this->normalizeBlocks(FlexArray::get($day, ['blocks'], [])),
            ];
        }

        return [
            'week_number' => (int) (FlexArray::get($week, ['week_number', 'week'], 1)),
            'title'       => (string) (FlexArray::get($week, ['title', 'name'], "Semana " . (FlexArray::get($week, ['week_number', 'week'], 1) ?? 1))),
            'days'        => $out,
        ];
    }

    private function normalizeBlocks(mixed $blocks): array
    {
        $out = [];
        foreach ((array) $blocks as $i => $block) {
            if (!is_array($block)) {
                continue;
            }
            $exercises = [];
            foreach ((array) (FlexArray::get($block, ['exercises'], []) ?? []) as $ex) {
                if (!is_array($ex)) {
                    continue;
                }
                $repsRaw = FlexArray::get($ex, ['reps', 'repetitions'], null);
                [$repsMin, $repsMax] = FlexArray::repsRange(is_array($repsRaw) ? null : $repsRaw);
                $exercises[] = [
                    'name'              => (string) (FlexArray::get($ex, ['name'], '')),
                    'source_exercise_id'=> FlexArray::get($ex, ['source_exercise_id', 'id'], null),
                    'equipment'         => FlexArray::get($ex, ['equipment'], null),
                    'muscles'           => (array) (FlexArray::get($ex, ['muscles'], []) ?? []),
                    'sets'              => (int) (FlexArray::get($ex, ['sets'], 1) ?? 1),
                    'reps_min'          => $repsMin,
                    'reps_max'          => $repsMax,
                    'rest_sec'          => FlexArray::seconds(FlexArray::get($ex, ['rest_sec', 'rest'], null), null),
                    'rpe_min'           => FlexArray::float($ex, ['rpe_min'], null),
                    'rpe_max'           => FlexArray::float($ex, ['rpe_max'], null),
                    'rir_min'           => FlexArray::float($ex, ['rir_min'], null),
                    'rir_max'           => FlexArray::float($ex, ['rir_max'], null),
                    'tempo'             => FlexArray::string($ex, ['tempo'], null),
                    'load_kg'           => FlexArray::float($ex, ['load_kg', 'weight'], null),
                    'weight_percent'    => FlexArray::float($ex, ['weight_percent', 'percentage'], null),
                    'notes'             => FlexArray::string($ex, ['notes'], null),
                ];
            }
            $out[] = [
                'title'        => (string) (FlexArray::get($block, ['title', 'name'], 'Parte principal')),
                'instructions' => FlexArray::get($block, ['instructions', 'notes'], null),
                'exercises'    => $exercises,
            ];
        }

        return $out;
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
