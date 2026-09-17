<?php

namespace App\Services;

use App\Models\ProgramDayAssignment;
use App\Models\TrainingProgram;
use App\Models\WorkoutTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2 de docs/PLAN_CLONADO_PROGRAMAS.md — clonado real de un
 * TrainingProgram de biblioteca (+ sus WorkoutTemplate/bloques/ejercicios)
 * para dejarle a un cliente concreto una copia exclusiva, en vez de que
 * `program_client_assignments` siga apuntando a la plantilla compartida
 * (bug raíz documentado en docs/PLAN_CLONADO_PROGRAMAS.md §1.1).
 *
 * Extiende un nivel arriba el mismo patrón de copia profunda que ya usa
 * `SectionTemplate::cloneInto()` (título + instrucciones + ejercicios,
 * copia real, no un enlace vivo).
 */
class ProgramCloningService
{
    /**
     * Clona `$library` entero para `$clientId`: el TrainingProgram, cada
     * WorkoutTemplate único referenciado por sus días de calendario (con
     * sus bloques y ejercicios), y cada ProgramDayAssignment apuntando ya
     * al WorkoutTemplate clonado correspondiente.
     */
    public function clone(TrainingProgram $library, int $clientId): TrainingProgram
    {
        return DB::transaction(function () use ($library, $clientId) {
            $clientCopy = $this->cloneTrainingProgram($library);

            // Mapa [workout_template_id original => WorkoutTemplate clon] --
            // clona cada plantilla referenciada UNA sola vez aunque varios
            // días de $library compartan el mismo workout_template_id.
            $templateCloneMap = [];

            foreach ($library->dayAssignments as $dayAssignment) {
                $originalTemplateId = $dayAssignment->workout_template_id;

                if ($originalTemplateId === null) {
                    // Día de descanso -- no hay plantilla que clonar.
                    $this->cloneDayAssignment($dayAssignment, $clientCopy->id, null);
                    continue;
                }

                if (!array_key_exists($originalTemplateId, $templateCloneMap)) {
                    $templateCloneMap[$originalTemplateId] = $this->cloneWorkoutTemplate(
                        $dayAssignment->workoutTemplate
                    );
                }

                $this->cloneDayAssignment(
                    $dayAssignment,
                    $clientCopy->id,
                    $templateCloneMap[$originalTemplateId]->id
                );
            }

            return $clientCopy;
        });
    }

    private function cloneTrainingProgram(TrainingProgram $library): TrainingProgram
    {
        return TrainingProgram::create([
            'title'                       => $library->title,
            'workout_id'                  => $library->workout_id,
            'coach_id'                    => $library->coach_id,
            // client_id/is_personal/personal_client_id son del sistema de
            // "calendario personal" (asignación directa día a día, ver
            // ClientProfileCalendarController::getOrCreatePersonalProgram),
            // un concepto distinto de este clon -- se dejan en su valor por
            // defecto, no se copian del library.
            'num_weeks'                   => $library->num_weeks,
            'fecha_inicio'                => $library->fecha_inicio,
            'fecha_fin'                   => $library->fecha_fin,
            'activo'                      => $library->activo,
            // is_free_accessible/billing_plan_id son gating de catálogo
            // (PackageAccessService) -- solo tienen sentido sobre la
            // plantilla de biblioteca, nunca sobre una copia exclusiva de
            // cliente (docs/PLAN_CLONADO_PROGRAMAS.md §1.5).
            // source/source_id (import hevy/strong/...) tampoco aplican al
            // clon: no es un import, es una copia derivada de otra fila ya
            // existente en esta misma base de datos.
            'source_training_program_id' => $library->id,
            'is_client_copy'              => true,
        ]);
    }

    private function cloneWorkoutTemplate(WorkoutTemplate $library): WorkoutTemplate
    {
        $clientCopy = WorkoutTemplate::create([
            'coach_id'                    => $library->coach_id,
            'title'                       => $library->title,
            'description'                 => $library->description,
            'is_exclusive'                => $library->is_exclusive,
            // is_demo es del WorkoutTemplate de bienvenida auto-asignado
            // (UserController::assignDemoWorkoutIfNeeded) -- fuera de
            // alcance de este clonado (docs/PLAN_CLONADO_PROGRAMAS.md §1.5),
            // una copia de cliente nunca es "el demo" en sí misma.
            'is_demo'                     => false,
            'source_workout_template_id' => $library->id,
            'is_client_copy'              => true,
        ]);

        foreach ($library->blocks as $block) {
            $clonedBlock = $clientCopy->blocks()->create([
                'source_section_template_id' => $block->source_section_template_id,
                'title'                       => $block->title,
                'instructions'                => $block->instructions,
                'order'                       => $block->order,
            ]);

            foreach ($block->exercises as $exercise) {
                $clonedBlock->exercises()->create([
                    'exercise_id'     => $exercise->exercise_id,
                    'sequence'        => $exercise->sequence,
                    'prescribed'      => $exercise->prescribed,
                    'enabled_metrics' => $exercise->enabled_metrics,
                    'notes'           => $exercise->notes,
                ]);
            }
        }

        return $clientCopy;
    }

    private function cloneDayAssignment(ProgramDayAssignment $original, int $clonedTrainingProgramId, ?int $clonedWorkoutTemplateId): ProgramDayAssignment
    {
        return ProgramDayAssignment::create([
            'training_program_id' => $clonedTrainingProgramId,
            'week_number'          => $original->week_number,
            'day_of_week'          => $original->day_of_week,
            'workout_template_id'  => $clonedWorkoutTemplateId,
            'is_deload'            => $original->is_deload,
            // scheduled_date y source_subscription_id son específicos de
            // ESTA fila original (cuándo cayó en el calendario de $library,
            // qué Subscription la generó ahí) -- no tienen sentido copiados
            // a la instancia del cliente, que resolverá su propia fecha vía
            // ProgramClientAssignment.start_date.
        ]);
    }
}
