<?php

namespace App\Console\Commands;

use App\Services\ProgramsImport\Adapters\CsvWorkoutAdapter;
use App\Services\ProgramsImport\Adapters\ExcelWorkoutAdapter;
use App\Services\ProgramsImport\Adapters\OpenWeightAdapter;
use App\Services\ProgramsImport\Adapters\WgerAdapter;
use App\Services\ProgramsImport\ImportJsonReport;
use App\Services\ProgramsImport\ProgramsImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Importa programas de entrenamiento desde fuentes externas (Hevy, Strong,
 * JEFIT vía CSV; openweight y wger vía JSON; plantilla propia vía Excel) a
 * training_programs + program_day_assignments + workout_templates,
 * resolviendo ejercicios con el matcher A-E y creando los que no matcheen.
 *
 *   php artisan programs:import hevy database/data/programs/hevy.example.csv --dry-run
 *   php artisan programs:import openweight storage/imports/programa.json --num-weeks=12
 *   php artisan programs:import excel database/data/programs/excel.example.xlsx --dry-run
 *   php artisan programs:import excel database/data/programs/excel.example.xlsx --dry-run --json
 *
 * `excel` es distinto de las demás fuentes: el propio archivo ya trae TODAS
 * las semanas explícitas (columna `semana` por fila), así que por defecto
 * NO se extrapola a --num-weeks=12 como con hevy/strong/jefit/wger -- se
 * usa el nº de semanas que traiga el archivo, salvo que pases --num-weeks
 * explícitamente para forzar otro valor.
 *
 * `--json` (ver docs/AGENTE_IMPORTADOR.md, sección 7): en vez del texto
 * pensado para consola humana, imprime un único JSON por stdout (ver
 * App\Services\ProgramsImport\ImportJsonReport) con los resultados, las
 * estadísticas, el reporte de ejercicios y `review_required` — la lista
 * aplanada de ejercicios con match ambiguo (nivel C/D/E) o auto-creados
 * que un agente automatizado debe mostrar a un humano antes de proceder
 * a un import real. No cambia el comportamiento del import en sí.
 */
class ImportProgramsCommand extends Command
{
    protected $signature = 'programs:import
        {source : Fuente: hevy | strong | jefit | openweight | wger | excel}
        {file : Ruta al archivo (CSV para hevy/strong/jefit; JSON para openweight/wger; XLSX para excel)}
        {--dry-run : Solo vista previa, no escribe en BD}
        {--report= : Ruta del CSV de reporte de ejercicios (por defecto database/data/programs/reports/)}
        {--threshold=0.72 : Confianza mínima del matcher (0-1)}
        {--num-weeks= : Semanas objetivo del programa (por defecto: 12 salvo excel, que usa las semanas del propio archivo)}
        {--progression=auto : auto | none (progresión semana a semana; sin efecto si num-weeks no supera las semanas de la fuente)}
        {--coach-id=1 : Coach asignado a las plantillas/programas}
        {--no-create : No crear ejercicios sin match (solo reportarlos)}
        {--force : Reimportar aunque ya exista (source, source_id)}
        {--free : Marcar el programa como is_free_accessible}
        {--json : Salida JSON estructurada por stdout en vez de texto para humano (ver ImportJsonReport)}';

    protected $description = 'Importa programas de entrenamiento (Hevy/Strong/JEFIT/openweight/wger/Excel) a la BD';

    public function handle(): int
    {
        $source = strtolower((string) $this->argument('source'));
        $file = (string) $this->argument('file');
        $jsonOutput = (bool) $this->option('json');

        $numWeeksOpt = $this->option('num-weeks');
        $numWeeks = $numWeeksOpt !== null ? (int) $numWeeksOpt : ($source === 'excel' ? 0 : 12);

        $adapter = match ($source) {
            'hevy', 'strong', 'jefit' => new CsvWorkoutAdapter($source),
            'openweight'              => new OpenWeightAdapter(),
            'wger'                    => new WgerAdapter($numWeeks),
            'excel'                   => new ExcelWorkoutAdapter(),
            default                   => null,
        };

        if ($adapter === null) {
            return $this->reportFailure($jsonOutput, "Fuente desconocida: {$source}. Usa hevy|strong|jefit|openweight|wger|excel.");
        }

        if (!is_file($file)) {
            return $this->reportFailure($jsonOutput, "Archivo no encontrado: {$file}");
        }

        $dryRun = (bool) $this->option('dry-run');
        if (!$jsonOutput) {
            $this->info("Fuente: {$source} | archivo: {$file}" . ($dryRun ? ' | DRY-RUN' : ''));
        }

        try {
            $canonical = $adapter->convert($file);
        } catch (\Throwable $e) {
            return $this->reportFailure($jsonOutput, 'Error parseando la fuente: ' . $e->getMessage());
        }

        $programCount = count($canonical['programs'] ?? []);
        if (!$jsonOutput) {
            $this->line("Programas detectados: {$programCount}");
        }

        // limpiar caché de firmas del matcher para reflejar ejercicios nuevos
        Cache::forget('exercise_matcher_db_signatures_v1');

        $importer = new ProgramsImporter(
            coachId: (int) $this->option('coach-id'),
            numWeeks: $numWeeks,
            progressionMode: (string) $this->option('progression'),
            threshold: (float) $this->option('threshold'),
            autoCreate: !$this->option('no-create'),
            dryRun: $dryRun,
            force: (bool) $this->option('force'),
            freeAccessible: (bool) $this->option('free'),
        );

        try {
            $result = $importer->import($canonical);
        } catch (\Throwable $e) {
            return $this->reportFailure($jsonOutput, 'Error importando: ' . $e->getMessage());
        }

        $reportCsvPath = $this->persistReport($importer->report(), $source);

        if ($jsonOutput) {
            $payload = ImportJsonReport::buildPayload($source, $file, $dryRun, $programCount, $result, $importer->report(), $reportCsvPath);
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderResults($result, $dryRun);
        if ($reportCsvPath === null) {
            $this->line('Reporte: sin ejercicios creados/no-matcheados.');
        } else {
            $this->info("Reporte de ejercicios: {$reportCsvPath} (" . count($importer->report()) . ' filas)');
        }

        return self::SUCCESS;
    }

    /** Reporta un fallo en el formato que corresponda ($jsonOutput) y devuelve el código de salida. */
    private function reportFailure(bool $jsonOutput, string $message): int
    {
        if ($jsonOutput) {
            $this->line(json_encode(ImportJsonReport::buildError($message), JSON_UNESCAPED_UNICODE));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }

    private function renderResults(array $result, bool $dryRun): void
    {
        foreach ((array) ($result['results'] ?? []) as $r) {
            $status = $r['status'] ?? '?';
            $title = $r['title'] ?? '';
            if ($status === 'skipped') {
                $this->warn("  [omitido] {$title} — " . ($r['reason'] ?? ''));
            } elseif ($status === 'dry-run') {
                $this->info("  [dry-run] {$title}");
                $this->renderPreview((array) ($r['preview'] ?? []));
            } else {
                $this->info("  [creado] {$title} → training_program #{$r['training_program_id']} ({$r['weeks']} semanas, {$r['assignments']} asignaciones)");
            }
        }

        $stats = (array) ($result['stats'] ?? []);
        if ($stats !== [] && !$dryRun) {
            $this->newLine();
            $this->info('Resumen:');
            foreach ($stats as $k => $v) {
                if (is_array($v)) {
                    $this->line("  {$k}: " . json_encode($v));
                } else {
                    $this->line("  {$k}: {$v}");
                }
            }
        }
    }

    private function renderPreview(array $preview): void
    {
        foreach ((array) ($preview['weeks'] ?? []) as $week) {
            if ((int) ($week['week_number'] ?? 0) > 2) {
                $this->line('    ... (semanas 3 en adelante omitidas en preview)');
                break;
            }
            $this->line("    Semana {$week['week_number']}:");
            foreach ((array) ($week['days'] ?? []) as $day) {
                if (($day['is_rest'] ?? false) || (($day['exercises'] ?? []) === [])) {
                    continue;
                }
                $this->line("      Día {$day['day_of_week']} — {$day['title']}:");
                foreach ((array) ($day['exercises'] ?? []) as $ex) {
                    $match = $ex['match'] ?? null;
                    $label = $match !== null
                        ? "→ {$match} (nivel {$ex['level']}, conf {$ex['confidence']})"
                        : '→ CREAR NUEVO';
                    $this->line("        {$ex['source']} {$label} " . json_encode($ex['prescribed'] ?? []));
                }
            }
        }
    }

    /** Escribe el CSV de ejercicios creados/no-matcheados y devuelve su ruta (null si no había nada que reportar). */
    private function persistReport(array $report, string $source): ?string
    {
        $explicitPath = $this->option('report');
        $path = ImportJsonReport::persistReportCsv($report, $source, $explicitPath ? (string) $explicitPath : null);

        if ($path === null && $report !== [] && !$this->option('json')) {
            $this->warn("No se pudo escribir el reporte en {$explicitPath}");
        }

        return $path;
    }
}
