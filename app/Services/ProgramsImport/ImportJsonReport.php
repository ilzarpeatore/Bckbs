<?php

namespace App\Services\ProgramsImport;

/**
 * Construye la salida JSON estructurada de `programs:import --json`, a
 * partir de las mismas estructuras que ya produce ProgramsImporter::import()
 * (no se toca el motor de import: toda la información ya existe, solo
 * faltaba una forma parseable de leerla en vez del texto pensado para
 * consola humana).
 *
 * Motivo (docs/AGENTE_IMPORTADOR.md, sección 7, punto 1): un agente
 * automatizado necesita distinguir programáticamente qué ejercicios
 * matchearon con confianza (nivel A/B) de los que necesitan revisión
 * humana (nivel C/D/E o creación automática) antes de escribir en
 * producción — sin tener que parsear texto de terminal.
 */
final class ImportJsonReport
{
    /**
     * @param array<string,mixed> $result   salida de ProgramsImporter::import()
     * @param array<int,array>    $report   salida de ProgramsImporter::report()
     */
    public static function buildPayload(
        string $source,
        string $file,
        bool $dryRun,
        int $programsDetected,
        array $result,
        array $report,
        ?string $reportCsvPath,
    ): array {
        $results = (array) ($result['results'] ?? []);

        return [
            'ok'                => true,
            'source'            => $source,
            'file'              => $file,
            'dry_run'           => $dryRun,
            'programs_detected' => $programsDetected,
            'results'           => $results,
            'stats'             => (array) ($result['stats'] ?? []),
            'review_required'   => self::buildReviewRequired($results),
            'report'            => $report,
            'report_csv_path'   => $reportCsvPath,
        ];
    }

    public static function buildError(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }

    /**
     * Payload de rechazo cuando `--confidence-gate` (CLI) / `confidence_gate`
     * (HTTP) bloquea un import real porque quedan ejercicios de nivel C/D/E o
     * auto-creados sin revisar (docs/AGENTE_IMPORTADOR.md, sección 7, punto
     * 5 -- "guardrail duro en código" en vez de que la pausa dependa solo de
     * que el LLM siga el system-prompt). No se ha escrito nada en BD.
     *
     * @param list<array<string,mixed>> $reviewRequired
     */
    public static function buildConfidenceGateBlocked(array $reviewRequired): array
    {
        return [
            'ok'    => false,
            'error' => 'confidence_gate_blocked',
            'message' => 'Import real bloqueado por confidence-gate: hay '.count($reviewRequired)
                .' ejercicio(s) con match ambiguo (nivel C/D/E) o auto-creado sin revisar. '
                .'No se ha escrito nada. Revisa review_required, corrige los nombres de ejercicio '
                .'o el catálogo, y repite -- o quita el guardrail si asumes el riesgo conscientemente.',
            'review_required' => $reviewRequired,
        ];
    }

    /**
     * Escribe el CSV de ejercicios creados/no-matcheados y devuelve su ruta
     * (null si no había nada que reportar, o si no se pudo escribir).
     * Extraído de ImportProgramsCommand para que el comando CLI y el
     * endpoint HTTP (ProgramImportController) escriban el mismo formato
     * exacto sin duplicar la lógica.
     *
     * @param array<int,array> $report salida de ProgramsImporter::report()
     */
    public static function persistReportCsv(array $report, string $source, ?string $explicitPath = null): ?string
    {
        if ($report === []) {
            return null;
        }

        $path = $explicitPath;
        if (!$path) {
            $dir = database_path('data/programs/reports');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $path = $dir . '/' . $source . '-' . date('Ymd-His') . '-report.csv';
        }

        $fh = fopen($path, 'w');
        if ($fh === false) {
            return null;
        }
        fputcsv($fh, ['source_exercise', 'exercise_id', 'resolved_title', 'candidates', 'action']);
        foreach ($report as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        return $path;
    }

    /**
     * Recorre las previsualizaciones de dry-run y extrae, aplanado, cada
     * ejercicio cuyo match no sea de nivel A o B (o que se auto-cree) --
     * exactamente el criterio de "pausa y pide aprobación humana" del
     * flujo del agente (sección 8 de docs/AGENTE_IMPORTADOR.md).
     *
     * Solo tiene sentido sobre resultados de dry-run: el import real no
     * devuelve el nivel de match por ejercicio (solo ids y contadores
     * agregados), y el flujo del agente exige revisar esto siempre en
     * dry-run, antes de escribir. Sobre un resultado de import real,
     * devuelve siempre [].
     *
     * @param array<int,array> $results
     * @return list<array<string,mixed>>
     */
    public static function buildReviewRequired(array $results): array
    {
        $items = [];

        foreach ($results as $result) {
            if (($result['status'] ?? null) !== 'dry-run') {
                continue;
            }

            $preview = (array) ($result['preview'] ?? []);
            $programTitle = $preview['title'] ?? null;

            foreach ((array) ($preview['weeks'] ?? []) as $week) {
                foreach ((array) ($week['days'] ?? []) as $day) {
                    foreach ((array) ($day['exercises'] ?? []) as $exercise) {
                        $level = $exercise['level'] ?? 'created';
                        if (in_array($level, ['A', 'B'], true)) {
                            continue;
                        }

                        $items[] = [
                            'program'              => $programTitle,
                            'week'                 => $week['week_number'] ?? null,
                            'day_of_week'          => $day['day_of_week'] ?? null,
                            'source_exercise'      => $exercise['source'] ?? null,
                            'level'                => $level,
                            'confidence'           => $exercise['confidence'] ?? null,
                            'matched_title'        => $exercise['match'] ?? null,
                            'matched_exercise_id'  => $exercise['match_id'] ?? null,
                        ];
                    }
                }
            }
        }

        return $items;
    }
}
