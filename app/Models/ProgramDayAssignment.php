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

    /**
     * AISLAMIENTO (garantía por construcción): al crear un día o cambiar su
     * plantilla, si esa plantilla la usa ya un propietario DISTINTO (la
     * biblioteca u otro cliente), el día recibe automáticamente su propia
     * copia (WorkoutTemplate::cloneStructure) en vez de compartir la fila.
     * Así ninguna ruta -- las de hoy (calendarios real/personal, generador de
     * semanas, alta de usuario con plantilla demo...) ni las que se añadan en
     * el futuro -- puede dejar a dos clientes (o a un cliente y la biblioteca)
     * apuntando a la misma plantilla. Varios días del MISMO propietario sí
     * pueden reutilizar una plantilla (p. ej. "Torso A" en dos semanas).
     * Ver TemplateIsolationGuard y el comando programs:audit-isolation.
     */
    protected static function booted(): void
    {
        static::saving(function (self $day) {
            if ($day->workout_template_id === null) {
                return;
            }
            if ($day->exists && !$day->isDirty('workout_template_id') && !$day->isDirty('training_program_id')) {
                return;
            }

            $program = TrainingProgram::find($day->training_program_id);
            if ($program === null) {
                return;
            }

            $owner = \App\Services\TemplateIsolationGuard::ownerKey($program);
            $others = array_diff(\App\Services\TemplateIsolationGuard::ownersOfTemplate((int) $day->workout_template_id), [$owner]);
            if ($others === []) {
                return;
            }

            $template = WorkoutTemplate::find($day->workout_template_id);
            if ($template !== null) {
                $day->workout_template_id = $template->cloneStructure()->id;
            }
        });
    }

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
