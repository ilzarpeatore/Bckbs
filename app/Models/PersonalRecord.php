<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'exercise_id', 'record_type', 'value',
        'achieved_at', 'workout_day_exercise_id',
    ];

    protected $casts = ['achieved_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }

    public function workoutDayExercise()
    {
        return $this->belongsTo(WorkoutDayExercise::class, 'workout_day_exercise_id', 'id');
    }

    /**
     * Fórmula de Epley para 1RM estimado: peso × (1 + reps/30).
     * Se usa desde el listener que se dispara al completar una sesión.
     */
    public static function calculateEpley1RM(float $weight, int $reps): float
    {
        return round($weight * (1 + $reps / 30), 2);
    }
}
