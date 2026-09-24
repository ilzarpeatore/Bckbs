<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramDayAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['training_program_id', 'week_number', 'day_of_week', 'workout_template_id', 'scheduled_date', 'is_deload'];

    protected $casts = ['scheduled_date' => 'date', 'is_deload' => 'boolean'];

    public function trainingProgram()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id', 'id');
    }

    public function workoutTemplate()
    {
        return $this->belongsTo(WorkoutTemplate::class, 'workout_template_id', 'id');
    }

    /**
     * (2026-09-24) Solo lo que planificó el coach: excluye los
     * entrenamientos creados por el propio cliente desde la app
     * (workout_template.created_by_client_id NOT NULL, ver
     * ClientCustomWorkoutController). Se usa en todo lo que mide
     * CUMPLIMIENTO del plan (adherencia, racha, compliance del riesgo de
     * abandono, semana adaptativa, "próxima sesión" del motor de carga):
     * saltarse un entrenamiento que el cliente se puso por su cuenta nunca
     * debe bajar su adherencia ni subir su riesgo.
     *
     * whereDoesntHave (y no whereHas(... whereNull)) para no cambiar nada en
     * filas cuya plantilla ya no existe o está borrada: siguen contando
     * exactamente igual que antes.
     */
    public function scopeCoachPlanned($query)
    {
        return $query->whereDoesntHave('workoutTemplate', fn ($t) => $t->whereNotNull('created_by_client_id'));
    }

    public function getIsRestAttribute(): bool
    {
        return is_null($this->workout_template_id);
    }
}
