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
}
