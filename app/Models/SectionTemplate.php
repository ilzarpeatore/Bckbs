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

    /**
     * Inverso de cloneInto(): crea una plantilla de sección a partir de un
     * bloque de un workout_template (título + instrucciones + ejercicios con
     * su prescrito y métricas). Es una COPIA: editar el bloque después no
     * modifica la sección ni al revés. Las notas por ejercicio no se copian
     * (section_template_exercises no tiene esa columna).
     */
    public static function createFromBlock(WorkoutTemplateBlock $block, int $coachId, ?string $title = null): self
    {
        $section = static::create([
            'coach_id'     => $coachId,
            'title'        => $title ?: ($block->title ?: 'Sección sin título'),
            'instructions' => $block->instructions,
        ]);

        foreach ($block->exercises as $exercise) {
            $section->exercises()->create([
                'exercise_id'     => $exercise->exercise_id,
                'sequence'        => $exercise->sequence,
                'prescribed'      => $exercise->prescribed,
                'enabled_metrics' => $exercise->enabled_metrics,
            ]);
        }

        return $section;
    }
}
