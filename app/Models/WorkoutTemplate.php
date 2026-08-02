<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class WorkoutTemplate extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = ['coach_id', 'title', 'description', 'is_exclusive'];

    protected $casts = [
        'is_exclusive' => 'boolean',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function blocks()
    {
        return $this->hasMany(WorkoutTemplateBlock::class, 'workout_template_id', 'id')
            ->orderBy('order');
    }

    /** Nº total de ejercicios (para la columna "Exercises" del listado, como en HubFit). */
    public function getExerciseCountAttribute(): int
    {
        return $this->blocks->sum(fn ($block) => $block->exercises->count());
    }
}
