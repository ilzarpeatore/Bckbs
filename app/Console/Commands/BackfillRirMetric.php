<?php

namespace App\Console\Commands;

use App\Models\SectionTemplateExercise;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Console\Command;

/**
 * RIR/RPE pasa a ser obligatorio (uno u otro) al registrar cualquier serie,
 * pero las plantillas ya importadas/creadas antes de este cambio tienen
 * `enabled_metrics` sin ninguno de los dos (por eso el motor de
 * auto-regulación nunca podía actuar, ver diagnóstico del usuario demo). Un
 * ejercicio ya asignado a un cliente real no vuelve a pasar por el
 * importador, así que hace falta este backfill puntual para que empiece a
 * capturar el dato desde la próxima sesión sin esperar a una reimportación.
 * No toca `client_exercise_logs` históricos.
 */
class BackfillRirMetric extends Command
{
    protected $signature = 'progression:backfill-rir-metric {--dry-run : Solo cuenta cuántas filas se actualizarían}';

    protected $description = 'Añade "rir" a enabled_metrics en workout_template_exercises / section_template_exercises que no tengan rir ni rpe';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ([WorkoutTemplateExercise::class, SectionTemplateExercise::class] as $model) {
            $count = 0;

            $model::query()->chunkById(200, function ($rows) use (&$count, $dryRun) {
                foreach ($rows as $row) {
                    $metrics = $row->enabled_metrics ?? [];
                    if (in_array('rir', $metrics, true) || in_array('rpe', $metrics, true)) {
                        continue;
                    }

                    $count++;
                    if (!$dryRun) {
                        $metrics[] = 'rir';
                        $row->update(['enabled_metrics' => $metrics]);
                    }
                }
            });

            $this->info("{$model}: {$count} filas " . ($dryRun ? 'a actualizar' : 'actualizadas') . '.');
            $total += $count;
        }

        $this->info("Total: {$total}.");

        return self::SUCCESS;
    }
}
