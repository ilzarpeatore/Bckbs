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

    protected $fillable = ['coach_id', 'title', 'description', 'is_exclusive', 'is_demo'];

    protected $casts = [
        'is_exclusive' => 'boolean',
        'is_demo'      => 'boolean',
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

    /**
     * Días de programa (import o generador) que usan esta plantilla. Sirve
     * para distinguir, en getList(), las plantillas sueltas/reutilizables
     * que el coach guarda a mano de las "instancias" que un import genera
     * una por cada combinación única de semana×día×progresión -- estas
     * últimas no están pensadas para navegarse en una lista plana (ver
     * RealCalendarController::getWeeksGrid() para verlas agrupadas por
     * programa/semana en su lugar).
     */
    public function programDayAssignments()
    {
        return $this->hasMany(ProgramDayAssignment::class, 'workout_template_id', 'id');
    }

    /** Nº total de ejercicios (para la columna "Exercises" del listado, como en HubFit). */
    public function getExerciseCountAttribute(): int
    {
        return $this->blocks->sum(fn ($block) => $block->exercises->count());
    }
}
