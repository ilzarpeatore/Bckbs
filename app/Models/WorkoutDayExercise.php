<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutDayExercise extends Model
{
    use HasFactory;

    protected $fillable = [ 'workout_id', 'workout_day_id','exercise_id', 'sets', 'sequence', 'duration'];

    protected $casts = [
        'workout_id'        => 'integer',
        'workout_day_id'    => 'integer',
        'exercise_id'       => 'integer',
        'sequence'          => 'integer',
    ];

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }

    public function scopeFindWorkoutDayExercise($query, $workout_id = null, $workout_day_id = null, $exercise_id = null) {

        $query->when($workout_id != null, function ($q) use($workout_id) {
            return $q->where('workout_id', $workout_id);
        });

        $query->when($workout_day_id != null, function ($q) use($workout_day_id) {
            return $q->where('workout_day_id', $workout_day_id);
        });

        $query->when($exercise_id != null, function ($q) use($exercise_id) {
            return $q->where('exercise_id', $exercise_id);
        });

        return $query;
    }

    public function getIsCompletedAttribute()
    {
        try {
            return UserExercise::myUserExercise()
                ->where('exercise_id', $this->exercise_id)
                ->where('workout_id', $this->workout_id)
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
    }
  
    public function workout()
    {
        return $this->belongsTo(Workout::class, 'workout_id');
    }

    public function workoutDay()
    {
        return $this->belongsTo(WorkoutDay::class, 'workout_day_id');
    }

}
