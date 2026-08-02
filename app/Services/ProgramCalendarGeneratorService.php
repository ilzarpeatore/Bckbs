<?php

namespace App\Services;

use App\Models\TrainingProgram;
use App\Models\ProgramDayAssignment;

class ProgramCalendarGeneratorService
{
    /**
     * REEMPLAZA a TrainingProgramGeneratorService (que clonaba
     * workout_days completos). Con el modelo nuevo, los workout_templates
     * son reutilizables, así que generar las semanas siguientes es mucho
     * más simple: solo se replica QUÉ workout_template va en cada
     * day_of_week de la Semana 1 — no se duplica ningún dato de
     * ejercicios. La progresión de carga se aplica dinámicamente al leer
     * (ver ProgramCalendarController::applyMultiplierToWorkout), no aquí.
     */
    public function generateFromWeekOne(TrainingProgram $program): array
    {
        $week_one = ProgramDayAssignment::where('training_program_id', $program->id)
            ->where('week_number', 1)
            ->get();

        if ($week_one->isEmpty()) {
            throw new \RuntimeException('No hay asignaciones en la Semana 1 de este programa — no hay nada de lo que partir.');
        }

        $created = [];

        for ($week = 2; $week <= $program->num_weeks; $week++) {
            foreach ($week_one as $sourceDay) {
                $created[] = ProgramDayAssignment::updateOrCreate(
                    [
                        'training_program_id' => $program->id,
                        'week_number'          => $week,
                        'day_of_week'          => $sourceDay->day_of_week,
                    ],
                    [
                        'workout_template_id' => $sourceDay->workout_template_id,
                    ]
                );
            }
        }

        return $created;
    }
}
