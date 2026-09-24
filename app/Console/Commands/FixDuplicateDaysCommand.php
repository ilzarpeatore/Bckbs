<?php

namespace App\Console\Commands;

use App\Models\ProgramDayAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpia filas duplicadas / sobrantes de program_day_assignments (soft delete,
 * recuperable). Caso real: "Mesociclo 1 OSAS Octubre" (programa 66) y todo lo
 * copiado a partir de él, con dos defectos que nacieron de operaciones del
 * calendario que APILABAN filas en vez de reemplazar (duplicateWeek /
 * duplicateWeekDay, ya corregidas):
 *
 *   1) Duplicados exactos: la misma plantilla dos veces en el mismo programa,
 *      semana y día (p. ej. la semana 4 entera duplicada).
 *   2) Sesión sobrante: un día con dos sesiones distintas, una de las cuales es
 *      justo la de ese mismo día de la semana SIGUIENTE (la semana 2 pegada por
 *      error sobre la semana 1) -- y la rejilla del admin solo muestra la
 *      primera, así que no se veía.
 *
 * Solo se borra una fila si NO tiene historial (revisiones de sesión, logs,
 * overrides de cliente); si la duplicada con historial es la única "buena" se
 * conserva esa. Lo que no se pueda decidir con seguridad se informa y no se toca.
 *
 *   php artisan programs:fix-duplicate-days --program=66            (dry-run)
 *   php artisan programs:fix-duplicate-days --all --apply
 */
class FixDuplicateDaysCommand extends Command
{
    protected $signature = 'programs:fix-duplicate-days
        {--program=* : ID(s) de training_program}
        {--all : Revisar todos los programas}
        {--apply : Aplicar (por defecto solo muestra qué haría)}';

    protected $description = 'Elimina (soft delete) días duplicados y sesiones sobrantes de un programa, sin tocar filas con historial';

    private function history(int $dayId): int
    {
        return DB::table('workout_session_reviews')->where('program_day_assignment_id', $dayId)->count()
            + DB::table('client_exercise_logs')->where('program_day_assignment_id', $dayId)->count()
            + DB::table('client_exercise_overrides')->where('program_day_assignment_id', $dayId)->count()
            + DB::table('client_block_overrides')->where('program_day_assignment_id', $dayId)->count();
    }

    public function handle(): int
    {
        $programIds = array_filter(array_map('intval', (array) $this->option('program')));
        if ($programIds === [] && !$this->option('all')) {
            $this->error('Indica --program=ID (repetible) o --all.');

            return self::FAILURE;
        }

        $query = ProgramDayAssignment::query();
        if ($programIds !== []) {
            $query->whereIn('training_program_id', $programIds);
        }
        $byProgram = $query->orderBy('id')->get()->groupBy('training_program_id');

        $toDelete = [];
        $conflicts = 0;

        foreach ($byProgram as $programId => $days) {
            $lines = [];

            // 1) Duplicados exactos (misma semana, día y plantilla; también los días de descanso vacíos).
            foreach ($days->groupBy(fn ($d) => $d->week_number.'-'.$d->day_of_week.'-'.($d->workout_template_id ?? 'null')) as $key => $group) {
                if ($group->count() < 2) {
                    continue;
                }
                $scored = $group->map(fn ($d) => ['day' => $d, 'h' => $this->history($d->id)])
                    ->sortBy([['h', 'desc'], ['day.id', 'asc']])->values();
                $keeper = $scored->first();
                foreach ($scored->slice(1) as $row) {
                    if ($row['h'] === 0) {
                        $toDelete[$row['day']->id] = true;
                        $lines[] = "  duplicado #{$row['day']->id} ({$key}) -> se elimina; se conserva #{$keeper['day']->id}";
                    } else {
                        $conflicts++;
                        $lines[] = "  CONFLICTO #{$row['day']->id} ({$key}) tiene historial; no se toca";
                    }
                }
            }

            // 2) Sesión sobrante: la de la semana siguiente pegada en este día.
            $live = $days->reject(fn ($d) => isset($toDelete[$d->id]))->filter(fn ($d) => $d->workout_template_id !== null);
            foreach ($live->groupBy(fn ($d) => $d->week_number.'-'.$d->day_of_week) as $key => $group) {
                if ($group->pluck('workout_template_id')->unique()->count() < 2) {
                    continue;
                }
                [$week, $dow] = array_map('intval', explode('-', $key));
                $nextTemplates = $live->where('week_number', $week + 1)->where('day_of_week', $dow)->pluck('workout_template_id')->unique();
                $isNext = fn ($d) => $nextTemplates->contains($d->workout_template_id);
                if ($group->contains(fn ($d) => !$isNext($d))) { // hay una sesión "propia" del día
                    foreach ($group->filter($isNext) as $stray) {
                        if ($this->history($stray->id) === 0) {
                            $toDelete[$stray->id] = true;
                            $lines[] = "  sobrante #{$stray->id} (sem {$week} día {$dow}, plantilla {$stray->workout_template_id} = la de la semana siguiente) -> se elimina";
                        } else {
                            $conflicts++;
                            $lines[] = "  CONFLICTO #{$stray->id} sobrante con historial; no se toca";
                        }
                    }
                }
            }

            if ($lines !== []) {
                $this->line("Programa #{$programId}:");
                foreach ($lines as $l) {
                    $this->line($l);
                }
            }
        }

        if ($toDelete === []) {
            $this->info('Nada que limpiar.'.($conflicts ? " ({$conflicts} conflicto(s) con historial sin tocar)" : ''));

            return self::SUCCESS;
        }

        if (!$this->option('apply')) {
            $this->info(count($toDelete).' fila(s) a eliminar. Ejecuta con --apply.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($toDelete) {
            ProgramDayAssignment::whereIn('id', array_keys($toDelete))->delete();
        });
        $this->info(count($toDelete).' fila(s) eliminadas (soft delete, recuperables).'.($conflicts ? " {$conflicts} conflicto(s) sin tocar." : ''));

        return self::SUCCESS;
    }
}
