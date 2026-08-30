<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Motor de Auto-Regulación de Carga — Fase 3 (documento §3.1). Variante de
 * ejercicio definida por el coach, consultada por SessionProgressionRuleEngine
 * cuando la acción ganadora es sustituir_ejercicio.
 */
class ExerciseSubstitution extends Model
{
    use HasFactory;

    protected $fillable = ['coach_id', 'original_exercise_id', 'substitute_exercise_id', 'category'];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function originalExercise()
    {
        return $this->belongsTo(Exercise::class, 'original_exercise_id', 'id');
    }

    public function substituteExercise()
    {
        return $this->belongsTo(Exercise::class, 'substitute_exercise_id', 'id');
    }
}
