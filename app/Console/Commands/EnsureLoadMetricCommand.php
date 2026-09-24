<?php

namespace App\Console\Commands;

use App\Models\ProgramDayAssignment;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Añade la métrica "carga" a los ejercicios de plantilla que no la tienen
 * habilitada, para que el cliente pueda anotar el peso usado en la app.
 *
 * Nace del caso "Mesociclo 1 TONI Septiembre": se importó ANTES del arreglo del
 * importador (commit ef63261, "activa 'carga' siempre en enabled_metrics"), así
 * que 44 de sus 54 ejercicios (press banca, hip thrust, jalón...) tenían solo
 * ["reps","descanso","rir"] y la app no mostraba el campo de peso.
 *
 *   php artisan programs:ensure-load-metric --program=116 --program=86 --program=48        (dry-run)
 *   php artisan programs:ensure-load-metric --program=116 --program=86 --program=48 --apply
 *   php artisan programs:ensure-load-metric --all                                          (todas las plantillas)
 *
 * "carga" se inserta justo después de "reps" (orden habitual ["reps","carga",...]);
 * el resto de métricas, y RIR/RPE, no se tocan. Idempotente.
 */
class EnsureLoadMetricCommand extends Command
{
    protected $signature = 'programs:ensure-load-metric
        {--program=* : ID(s) de training_program cuyas plantillas se revisan}
        {--all : Revisar todas las plantillas de ejercicio}
        {--apply : Aplicar los cambios (por defecto solo muestra qué haría)}';

    protected $description = 'Habilita la métrica "carga" en los ejercicios de plantilla que no la tienen (para poder registrar el peso)';

    public function handle(): int
    {
        $programIds = array_filter(array_map('intval', (array) $this->option('program')));
        if ($programIds === [] && !$this->option('all')) {
            $this->error('Indica --program=ID (repetible) o --all.');

            return self::FAILURE;
        }

        $query = WorkoutTemplateExercise::query();
        if ($programIds !== []) {
            $templateIds = ProgramDayAssignment::whereIn('training_program_id', $programIds)
                ->whereNotNull('workout_template_id')->pluck('workout_template_id')->unique();
            $query->whereHas('block', fn ($q) => $q->whereIn('workout_template_id', $templateIds));
        }

        $todo = $query->get()->filter(function (WorkoutTemplateExercise $e) {
            $m = $e->enabled_metrics;

            return is_array($m) && $m !== [] && !in_array('carga', $m, true);
        });

        if ($todo->isEmpty()) {
            $this->info('Todos los ejercicios revisados ya tienen "carga" habilitada.');

            return self::SUCCESS;
        }

        $byPattern = $todo->groupBy(fn ($e) => json_encode($e->enabled_metrics));
        foreach ($byPattern as $pattern => $rows) {
            $this->line(sprintf('%d ejercicio(s) con %s', $rows->count(), $pattern));
        }

        if (!$this->option('apply')) {
            $this->info($todo->count().' ejercicio(s) pendientes de habilitar "carga". Ejecuta con --apply.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($todo) {
            foreach ($todo as $e) {
                $metrics = array_values($e->enabled_metrics);
                $at = array_search('reps', $metrics, true);
                array_splice($metrics, $at === false ? 0 : $at + 1, 0, ['carga']);
                $e->update(['enabled_metrics' => $metrics]);
            }
        });

        $this->info($todo->count().' ejercicio(s) actualizados: ahora pueden registrar carga.');

        return self::SUCCESS;
    }
}
