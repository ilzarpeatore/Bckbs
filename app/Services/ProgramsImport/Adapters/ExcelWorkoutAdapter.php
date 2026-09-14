<?php

namespace App\Services\ProgramsImport\Adapters;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Adaptador de la plantilla Excel propia (ver
 * database/data/programs/excel.example.xlsx), pensada para que un agente IA
 * la rellene directamente a partir de un objetivo ("4 semanas, 5 sesiones
 * por semana...") sin pasar por el formato "una fila por serie" de
 * Hevy/Strong/JEFIT.
 *
 * Dos hojas:
 *  - "Programa": título/descripción del programa (fila 1 cabecera, fila 2 valores).
 *  - "Programación": 1 fila por ejercicio (o 1 fila por día de descanso),
 *    con columna `semana` explícita -- así el propio archivo trae TODAS las
 *    semanas ya escritas, sin depender de AutoProgression.
 *
 * A diferencia de CsvWorkoutAdapter/WgerAdapter (que traen menos semanas de
 * las necesarias y dejan que ProgramsImporter repita+progresione la
 * última), aquí num_weeks = nº de semanas distintas presentes en el
 * archivo, así que ImportProgramsCommand debe pasar numWeeks=0 (o el mismo
 * nº) para que ProgramsImporter NO intente extrapolar semanas de más.
 *
 * Días sin ninguna fila en una semana no hace falta escribirlos: los rellena
 * como descanso automáticamente ProgramsImporter::expandDays().
 */
final class ExcelWorkoutAdapter implements AdapterInterface
{
    public function convert(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException("El archivo Excel no existe: {$filePath}");
        }

        $spreadsheet = IOFactory::load($filePath);

        $programSheet = $spreadsheet->getSheetByName('Programa');
        $gridSheet = $spreadsheet->getSheetByName('Programación') ?? $spreadsheet->getSheetByName('Rutina');
        if ($gridSheet === null) {
            throw new \RuntimeException('No se encontró la hoja "Programación" (ni "Rutina") en el Excel.');
        }

        [$title, $description] = $this->readProgramSheet($programSheet);
        $rows = $this->readGridRows($gridSheet);
        $weeks = $this->groupIntoWeeks($rows);

        if ($weeks === []) {
            throw new \RuntimeException('La hoja "Programación" no tiene filas válidas (revisa columnas "semana" y "dia").');
        }

        return [
            'programs' => [[
                'source'      => 'excel',
                'source_id'   => 'excel-' . sha1_file($filePath),
                'title'       => $title !== '' ? $title : 'Programa importado (Excel)',
                'description' => $description,
                'num_weeks'   => count($weeks),
                'weeks'       => array_values($weeks),
            ]],
        ];
    }

    /** Hoja "Programa": cabeceras en fila 1 (titulo, descripcion, semanas), valores en fila 2. */
    private function readProgramSheet(?Worksheet $sheet): array
    {
        if ($sheet === null) {
            return ['', null];
        }
        $data = $sheet->toArray(null, true, true, false);
        if (count($data) < 2) {
            return ['', null];
        }
        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $data[0]);
        $values = $data[1];
        $map = array_combine($header, $values);

        $title = trim((string) ($map['titulo'] ?? ''));
        $description = $map['descripcion'] ?? null;
        $description = $description !== null && trim((string) $description) !== '' ? (string) $description : null;

        return [$title, $description];
    }

    /** Hoja "Programación": cabeceras en fila 1, cada fila siguiente = un ejercicio o un día de descanso. */
    private function readGridRows(Worksheet $sheet): array
    {
        $data = $sheet->toArray(null, true, true, false);
        if ($data === []) {
            return [];
        }
        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), array_shift($data));

        $rows = [];
        foreach ($data as $line) {
            $row = [];
            foreach ($header as $i => $col) {
                if ($col === '') {
                    continue;
                }
                $row[$col] = $line[$i] ?? null;
            }
            $isEmpty = implode('', array_map(fn ($v) => trim((string) ($v ?? '')), $row)) === '';
            if ($isEmpty) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));

        return strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    }

    /**
     * Agrupa las filas planas de la hoja en weeks[].days[].blocks[].exercises[],
     * respetando el orden de aparición para `sequence` de ejercicios y `order`
     * de bloques (mismo criterio que el resto de adaptadores).
     */
    private function groupIntoWeeks(array $rows): array
    {
        $weeks = []; // week_number => ['week_number', 'days' => [dow => day]]

        foreach ($rows as $row) {
            $weekNum = FlexArray::int($row, ['semana'], null);
            $dow = FlexArray::int($row, ['dia', 'día'], null);
            if ($weekNum === null || $weekNum < 1 || $dow === null || $dow < 1 || $dow > 7) {
                continue; // fila inválida (falta semana/dia o fuera de rango): se ignora
            }

            if (!isset($weeks[$weekNum])) {
                $weeks[$weekNum] = ['week_number' => $weekNum, 'days' => []];
            }
            if (!isset($weeks[$weekNum]['days'][$dow])) {
                $weeks[$weekNum]['days'][$dow] = [
                    'day_of_week' => $dow,
                    'title'       => FlexArray::string($row, ['nombre_dia'], 'Día ' . $dow),
                    'description' => null,
                    'is_rest'     => false,
                    'blocks'      => [], // blockTitle => block (preserva orden de inserción)
                ];
            }

            $dayNotes = FlexArray::string($row, ['notas_dia'], null);
            if ($dayNotes !== null) {
                $weeks[$weekNum]['days'][$dow]['description'] = $dayNotes;
            }
            if (FlexArray::bool($row, ['es_descanso'], false)) {
                $weeks[$weekNum]['days'][$dow]['is_rest'] = true;
                continue; // fila de descanso: no aporta ejercicio
            }

            $exerciseName = FlexArray::string($row, ['ejercicio'], null);
            if ($exerciseName === null || trim($exerciseName) === '') {
                continue; // fila sin ejercicio y sin marcar descanso: se ignora
            }

            $blockTitle = (string) FlexArray::string($row, ['bloque'], 'Parte principal');
            $blockInstructions = FlexArray::string($row, ['instrucciones_bloque'], null);

            $blocks = &$weeks[$weekNum]['days'][$dow]['blocks'];
            if (!isset($blocks[$blockTitle])) {
                $blocks[$blockTitle] = [
                    'title'        => $blockTitle,
                    'instructions' => $blockInstructions,
                    'exercises'    => [],
                ];
            } elseif ($blockInstructions !== null) {
                $blocks[$blockTitle]['instructions'] = $blockInstructions;
            }

            [$repsMin, $repsMax] = FlexArray::repsRange(FlexArray::string($row, ['reps'], null));
            [$rirMin, $rirMax] = FlexArray::floatRange(FlexArray::string($row, ['rir'], null));
            [$rpeMin, $rpeMax] = FlexArray::floatRange(FlexArray::string($row, ['rpe'], null));

            $blocks[$blockTitle]['exercises'][] = [
                'name'               => trim($exerciseName),
                'source_exercise_id' => null,
                'equipment'          => FlexArray::string($row, ['equipo'], null),
                'muscles'            => [],
                'sets'               => FlexArray::int($row, ['series'], null),
                'reps_min'           => $repsMin,
                'reps_max'           => $repsMax,
                'rest_sec'           => FlexArray::int($row, ['descanso_seg'], null),
                'rpe_min'            => $rpeMin,
                'rpe_max'            => $rpeMax,
                'rir_min'            => $rirMin,
                'rir_max'            => $rirMax,
                'tempo'              => FlexArray::string($row, ['tempo'], null),
                'load_kg'            => FlexArray::float($row, ['carga_kg'], null),
                'weight_percent'     => FlexArray::float($row, ['carga_pct'], null),
                'notes'              => FlexArray::string($row, ['notas'], null),
                'duration_sec'       => FlexArray::int($row, ['duracion_seg'], null),
            ];
            unset($blocks);
        }

        $out = [];
        foreach ($weeks as $weekNum => $week) {
            $days = $week['days'];
            ksort($days);
            $dayList = [];
            foreach ($days as $day) {
                $day['blocks'] = array_values($day['blocks']);
                $dayList[] = $day;
            }
            $out[$weekNum] = ['week_number' => $weekNum, 'days' => $dayList];
        }
        ksort($out);

        return $out;
    }
}
