<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramDayAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['training_program_id', 'week_number', 'day_of_week', 'workout_template_id', 'scheduled_date'];

    protected $casts = ['scheduled_date' => 'date'];

    public function trainingProgram()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id', 'id');
    }

    public function workoutTemplate()
    {
        return $this->belongsTo(WorkoutTemplate::class, 'workout_template_id', 'id');
    }

    public function getIsRestAttribute(): bool
    {
        return is_null($this->workout_template_id);
    }
}
