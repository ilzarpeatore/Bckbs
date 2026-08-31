<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientExerciseOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_day_assignment_id', 'client_id', 'workout_template_exercise_id',
        'prescribed_override', 'enabled_metrics_override', 'notes', 'hidden',
    ];

    protected $casts = [
        'prescribed_override'         => 'array',
        'enabled_metrics_override'    => 'array',
        'hidden'                      => 'boolean',
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
}
