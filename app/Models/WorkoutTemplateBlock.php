<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkoutTemplateBlock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['workout_template_id', 'source_section_template_id', 'title', 'instructions', 'order'];

    public function workoutTemplate()
    {
        return $this->belongsTo(WorkoutTemplate::class, 'workout_template_id', 'id');
    }

    public function sourceSection()
    {
        return $this->belongsTo(SectionTemplate::class, 'source_section_template_id', 'id');
    }

    public function exercises()
    {
        return $this->hasMany(WorkoutTemplateExercise::class, 'workout_template_block_id', 'id')
            ->orderBy('sequence');
    }
}
