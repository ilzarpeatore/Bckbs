<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkoutTemplateExercise extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['workout_template_block_id', 'exercise_id', 'sequence', 'prescribed', 'enabled_metrics', 'notes'];

    protected $casts = [
        'prescribed'      => 'array',
        'enabled_metrics' => 'array',
    ];

    public function block()
    {
        return $this->belongsTo(WorkoutTemplateBlock::class, 'workout_template_block_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }
}
