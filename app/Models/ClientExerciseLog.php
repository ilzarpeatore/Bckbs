<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientExerciseLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'workout_template_exercise_id', 'exercise_id',
        'program_day_assignment_id', 'performed_date', 'logged_sets', 'notes',
    ];

    protected $casts = [
        'logged_sets'    => 'array',
        'performed_date' => 'date',
    ];

    /**
     * Solo el ÚLTIMO registro de cada ejercicio en cada sesión.
     *
     * La app (workout_session_screen.tsx::syncExerciseLog) manda, cada vez
     * que se marca o desmarca una serie, TODAS las series ya completadas de
     * ese ejercicio -- y logSets() crea una fila nueva cada vez. 3 series
     * marcadas una a una = 3 filas con 1, 2 y 3 series: sumarlas todas
     * contaba 6 series y el doble de volumen (medido, 2026-09-24). Cada
     * fila es una "foto" acumulada; la última es el estado final real.
     *
     * Sesión = program_day_assignment_id (un día concreto del calendario;
     * sin fecha para no partir una sesión que cruza la medianoche) o, en
     * entrenamientos sueltos sin asignación, el día (performed_date).
     * workout_template_exercise_id separa un mismo ejercicio que aparece
     * dos veces en la plantilla.
     *
     * Usar en todo lo que SUMA o LISTA series (volumen, rankings,
     * historiales, notas). Lo que ya coge el máximo o el último registro no
     * lo necesita.
     */
    public function scopeLatestSnapshots($query, int $clientId)
    {
        return $query->whereIn('id', function ($sub) use ($clientId) {
            $sub->selectRaw('MAX(id)')
                ->from('client_exercise_logs')
                ->where('client_id', $clientId)
                ->groupBy('exercise_id', 'workout_template_exercise_id', 'program_day_assignment_id')
                ->groupByRaw('CASE WHEN program_day_assignment_id IS NULL THEN performed_date END');
        });
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }

    public function workoutTemplateExercise()
    {
        return $this->belongsTo(WorkoutTemplateExercise::class, 'workout_template_exercise_id', 'id');
    }
}
