<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutSessionReview extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'workout_day_id', 'program_day_assignment_id', 'workout_template_id', 'difficulty_rating', 'comment', 'completed_at', 'duration_seconds', 'volume_kg', 'calories_burned'];

    protected $casts = ['completed_at' => 'datetime', 'duration_seconds' => 'integer', 'volume_kg' => 'float', 'calories_burned' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function workoutDay()
    {
        return $this->belongsTo(WorkoutDay::class, 'workout_day_id', 'id');
    }

    public function programDayAssignment()
    {
        return $this->belongsTo(ProgramDayAssignment::class, 'program_day_assignment_id', 'id');
    }

    public function workoutTemplate()
    {
        return $this->belongsTo(WorkoutTemplate::class, 'workout_template_id', 'id');
    }

    /**
     * true si la sesión cerrada es un entrenamiento que creó el PROPIO
     * cliente desde la app (workout_template.created_by_client_id, ver
     * ClientCustomWorkoutController) -- por día de calendario o, en un
     * workout suelto, por la plantilla directa.
     *
     * (2026-09-24) Esas sesiones NO alimentan el Motor de Auto-Regulación de
     * Carga: el prescrito lo tecleó el cliente, no el coach, así que ni
     * sirve para evaluar reglas de progresión ni debe llenar la cola de
     * sugerencias pendientes del coach. Ver finishSession() y
     * SessionInterpretationService::processReview().
     */
    public function isClientCustomSession(): bool
    {
        $templateId = $this->workout_template_id;
        if ($this->program_day_assignment_id) {
            $templateId = ProgramDayAssignment::withTrashed()
                ->whereKey($this->program_day_assignment_id)
                ->value('workout_template_id');
        }
        $template = $templateId ? WorkoutTemplate::withTrashed()->find($templateId) : null;

        return $template !== null && $template->created_by_client_id !== null;
    }
}
