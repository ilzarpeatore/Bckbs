<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['coach_id', 'title', 'instructions'];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function exercises()
    {
        return $this->hasMany(SectionTemplateExercise::class, 'section_template_id', 'id')
            ->orderBy('sequence');
    }

    /**
     * Clona esta sección (título + ejercicios con su prescrito y
     * métricas) dentro de un workout_template — es una COPIA, no un
     * enlace en vivo. Igual que "importar" una Section en HubFit.
     */
    public function cloneInto(WorkoutTemplate $workoutTemplate, int $order = 0): WorkoutTemplateBlock
    {
        $block = $workoutTemplate->blocks()->create([
            'source_section_template_id' => $this->id,
            'title'        => $this->title,
            'instructions' => $this->instructions,
            'order'        => $order,
        ]);

        foreach ($this->exercises as $sectionExercise) {
            $block->exercises()->create([
                'exercise_id'      => $sectionExercise->exercise_id,
                'sequence'         => $sectionExercise->sequence,
                'prescribed'       => $sectionExercise->prescribed,
                'enabled_metrics'  => $sectionExercise->enabled_metrics,
            ]);
        }

        return $block;
    }
}
