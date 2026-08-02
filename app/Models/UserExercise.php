<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserExercise extends Model
{
    use HasFactory;

    protected $fillable = [ 'user_id', 'workout_id', 'exercise_id', 'workout_day_id', 'logged_sets', 'date' ];

    protected $casts = [
        'user_id'      => 'integer',
        'exercise_id'   => 'integer',
        'logged_sets'   => 'array',
        'date'          => 'date:Y-m-d',
    ];

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }
    public function workout()
    {
        return $this->belongsTo(Workout::class, 'workout_id', 'id');
    }

    public function scopeMyUserExercise($query)
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return false;
        }
        return $query->where('user_id', $user->id);
    }
    public function scopeForUser($query, $userId)
    {
        if (empty($userId)) {
            return $query;
        }
        return $query->where('user_id', $userId);
    }

    public function scopeWorkoutHistory($query)
    {
        return $query->whereNotNull('workout_id')->whereNotNull('workout_day_id');
    }
    public function workoutDay()
    {
        return $this->belongsTo(WorkoutDay::class, 'workout_day_id', 'id');
    }
}
