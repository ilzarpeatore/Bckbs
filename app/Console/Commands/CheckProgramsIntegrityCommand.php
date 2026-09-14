<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use App\Services\ExerciseMatcher\ExerciseMatcher;
use App\Services\ProgramsImport\ProgramsImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;

/**
 * Detecta (y opcionalmente repara) filas de workout_template_exercises cuyo
 * exercise_id no resuelve a un Exercise activo -- referencia "rota" por
 * soft-delete del ejercicio matcheado en un import, o por cualquier otra
 * causa futura de la misma forma (FK apuntando a un registro borrado o
 * inexistente).
 *
 * Nace del bug real encontrado el 2026-09-14: ExerciseMatcher incluía
 * ejercicios soft-deleted como candidatos de match (ver
 * app/Services/ExerciseMatcher/ExerciseMatcher.php::loadDbSignatures, ya
 * corregido). Este comando es la red de seguridad para esa MISMA clase de
 * problema si vuelve a aparecer por otra vía -- no depende de que alguien
 * note manualmente un ejercicio "fantasma" en la app.
 *
 *   php artisan programs:check-integrity           (solo reporta)
 *   php artisan programs:check-integrity --fix      (repara también)
 */
class CheckProgramsIntegrityCommand extends Command
{
    protected $signature = 'programs:check-integrity
        {--fix : Reparar automáticamente las referencias rotas}
        {--coach-id=1 : Coach usado si hace falta crear un ejercicio nuevo al reparar}';

    protected $description = 'Detecta (y opcionalmente repara) referencias rotas (exercise_id borrado/inexistente) en workout_template_exercises';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        $activeIds = Exercise::pluck('id')->flip();
        $rows = WorkoutTemplateExercise::all();
        $broken = $rows->filter(fn ($r) => !$activeIds->has($r->exercise_id));

        if ($broken->isEmpty()) {
            $this->info('Sin referencias rotas. Todo correcto (' . $rows->count() . ' filas revisadas).');
            return self::SUCCESS;
        }

        $byExerciseId = $broken->groupBy('exercise_id');
        $this->warn("Encontradas {$broken->count()} fila(s) rota(s), en {$byExerciseId->count()} ejercicio(s) distinto(s):");
        $this->newLine();

        $matcher = new ExerciseMatcher(0.72);
        $importer = new ProgramsImporter(coachId: (int) $this->option('coach-id'), numWeeks: 1);
        $createExercise = new ReflectionMethod($importer, 'createExercise');
        $createExercise->setAccessible(true);

        foreach ($byExerciseId as $oldId => $group) {
            $trashed = Exercise::withTrashed()->find($oldId);
            $label = $trashed !== null
                ? "\"{$trashed->title}\" (borrado el " . ($trashed->deleted_at?->toDateString() ?? '?') . ')'
                : 'REGISTRO INEXISTENTE (no queda título del que recuperarse)';
            $this->line("exercise_id={$oldId} → {$label} — {$group->count()} fila(s)");

            foreach ($group as $row) {
                $block = WorkoutTemplateBlock::find($row->workout_template_block_id);
                $tpl = $block !== null ? WorkoutTemplate::find($block->workout_template_id) : null;
                $this->line('    wte_id=' . $row->id . ' · ' . ($tpl !== null ? $tpl->title : '(plantilla no encontrada)'));
            }

            if (!$fix) {
                continue;
            }

            if ($trashed === null) {
                $this->error('    No reparable automáticamente: revisar a mano.');
                $this->newLine();
                continue;
            }

            $equipment = $trashed->equipment_id ? Equipment::find($trashed->equipment_id)?->title : null;
            $match = $matcher->match($trashed->title, $equipment, [], $equipment);

            if ($match !== null) {
                $newId = (int) $match['exercise']->id;
                $this->info("    → reparado con match existente: #{$newId} {$match['exercise']->title} (nivel {$match['level']})");
            } else {
                $newExercise = $createExercise->invoke(
                    $importer,
                    $trashed->title,
                    $equipment,
                    [],
                    ['notes' => null, 'source_hint' => 'programs:check-integrity --fix'],
                );
                $newId = (int) $newExercise->id;
                $this->info("    → creado ejercicio nuevo: #{$newId} {$newExercise->title}");
            }

            WorkoutTemplateExercise::where('exercise_id', $oldId)->update(['exercise_id' => $newId]);
            $this->newLine();
        }

        if ($fix) {
            Cache::forget('exercise_matcher_db_signatures_v1');
            $this->info('Reparación completada.');
        } else {
            $this->newLine();
            $this->comment('Ejecuta con --fix para reparar automáticamente.');
        }

        return self::SUCCESS;
    }
}
