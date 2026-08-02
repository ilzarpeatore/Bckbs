<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientExerciseLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'workout_template_exercise_id', 'exercise_id',
        'program_day_assignment_id', 'performed_date', 'logged_sets', 'notes',
    ];

    protected $casts = [
        'logged_sets'    => 'array',
        'performed_date' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }

    public function workoutTemplateExercise()
    {
        return $this->belongsTo(WorkoutTemplateExercise::class, 'workout_template_exercise_id', 'id');
    }
}
