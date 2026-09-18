<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientExerciseOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_day_assignment_id', 'client_id', 'workout_template_exercise_id',
        'prescribed_override', 'enabled_metrics_override', 'notes', 'hidden', 'no_recortable',
        // AÑADIDO (auditoría 2026-09-18): representan un ejercicio añadido
        // solo para este cliente, no un override de uno ya existente en la
        // plantilla -- ver migración add_addition_columns_to_client_exercise_overrides_table.
        'workout_template_block_id', 'client_block_override_id', 'exercise_id', 'sequence',
    ];

    protected $casts = [
        'prescribed_override'         => 'array',
        'enabled_metrics_override'    => 'array',
        'hidden'                      => 'boolean',
        'no_recortable'               => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function assignment()
    {
        return $this->belongsTo(ProgramDayAssignment::class, 'program_day_assignment_id', 'id');
    }

    public function workoutTemplateExercise()
    {
        return $this->belongsTo(WorkoutTemplateExercise::class, 'workout_template_exercise_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }

    public function workoutTemplateBlock()
    {
        return $this->belongsTo(WorkoutTemplateBlock::class, 'workout_template_block_id', 'id');
    }

    public function clientBlock()
    {
        return $this->belongsTo(ClientBlockOverride::class, 'client_block_override_id', 'id');
    }

    /** true si esta fila representa un ejercicio añadido por el cliente, no un override de uno existente. */
    public function getIsAdditionAttribute(): bool
    {
        return is_null($this->workout_template_exercise_id);
    }
}
