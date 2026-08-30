<?php

namespace App\Console\Commands;

use App\Services\ProgramsImport\Adapters\CsvWorkoutAdapter;
use App\Services\ProgramsImport\Adapters\OpenWeightAdapter;
use App\Services\ProgramsImport\Adapters\WgerAdapter;
use App\Services\ProgramsImport\ProgramsImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Importa programas de entrenamiento desde fuentes externas (Hevy, Strong,
 * JEFIT vía CSV; openweight y wger vía JSON) a training_programs +
 * program_day_assignments + workout_templates, resolviendo ejercicios con el
 * matcher A-E y creando los que no matcheen.
 *
 *   php artisan programs:import hevy database/data/programs/hevy.example.csv --dry-run
 *   php artisan programs:import openweight storage/imports/programa.json --num-weeks=12
 */
class ImportProgramsCommand extends Command
{
    protected $signature = 'programs:import
        {source : Fuente: hevy | strong | jefit | openweight | wger}
        {file : Ruta al archivo (CSV para hevy/strong/jefit; JSON para openweight/wger)}
        {--dry-run : Solo vista previa, no escribe en BD}
        {--report= : Ruta del CSV de reporte de ejercicios (por defecto database/data/programs/reports/)}
        {--threshold=0.72 : Confianza mínima del matcher (0-1)}
        {--num-weeks=12 : Semanas objetivo del programa}
        {--progression=auto : auto | none (progresión semana a semana)}
        {--coach-id=1 : Coach asignado a las plantillas/programas}
        {--no-create : No crear ejercicios sin match (solo reportarlos)}
        {--force : Reimportar aunque ya exista (source, source_id)}
        {--free : Marcar el programa como is_free_accessible}';

    protected $description = 'Importa programas de entrenamiento (Hevy/Strong/JEFIT/openweight/wger) a la BD';

    public function handle(): int
    {
        $source = strtolower((string) $this->argument('source'));
        $file = (string) $this->argument('file');

        $adapter = match ($source) {
            'hevy', 'strong', 'jefit' => new CsvWorkoutAdapter($source),
            'openweight'              => new OpenWeightAdapter(),
            'wger'                    => new WgerAdapter((int) $this->option('num-weeks')),
            default                   => null,
        };

        if ($adapter === null) {
            $this->error("Fuente desconocida: {$source}. Usa hevy|strong|jefit|openweight|wger.");
            return self::FAILURE;
        }

        if (!is_file($file)) {
            $this->error("Archivo no encontrado: {$file}");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info("Fuente: {$source} | archivo: {$file}" . ($dryRun ? ' | DRY-RUN' : ''));

        try {
            $canonical = $adapter->convert($file);
        } catch (\Throwable $e) {
            $this->error('Error parseando la fuente: ' . $e->getMessage());
            return self::FAILURE;
        }

        $programCount = count($canonical['programs'] ?? []);
        $this->line("Programas detectados: {$programCount}");

        // limpiar caché de firmas del matcher para reflejar ejercicios nuevos
        Cache::forget('exercise_matcher_db_signatures_v1');

        $importer = new ProgramsImporter(
            coachId: (int) $this->option('coach-id'),
            numWeeks: (int) $this->option('num-weeks'),
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
            $this->error('Error importando: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->renderResults($result, $dryRun);
        $this->writeReport($importer->report(), $source);

        return self::SUCCESS;
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

    private function writeReport(array $report, string $source): void
    {
        if ($report === []) {
            $this->line('Reporte: sin ejercicios creados/no-matcheados.');
            return;
        }

        $path = $this->option('report');
        if (!$path) {
            $dir = database_path('data/programs/reports');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $path = $dir . '/' . $source . '-' . date('Ymd-His') . '-report.csv';
        }

        $fh = fopen((string) $path, 'w');
        if ($fh === false) {
            $this->warn("No se pudo escribir el reporte en {$path}");
            return;
        }
        fputcsv($fh, ['source_exercise', 'exercise_id', 'resolved_title', 'candidates', 'action']);
        foreach ($report as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        $this->info("Reporte de ejercicios: {$path} (" . count($report) . ' filas)');
    }
}
