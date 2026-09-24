<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientExerciseLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'workout_template_exercise_id', 'exercise_id',
        'program_day_assignment_id', 'session_key', 'performed_date', 'logged_sets', 'notes',
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
     * Sesión, por orden de prioridad:
     * 1. program_day_assignment_id (un día concreto del calendario; sin
     *    fecha para no partir una sesión que cruza la medianoche). Es la
     *    misma identidad de sesión que usan finishSession() (una review por
     *    asignación) y SessionInterpretationService, así que manda aunque
     *    venga session_key: si la app se cierra a mitad y se reabre el mismo
     *    día con una session_key nueva, reenviar las series no las duplica.
     * 2. session_key (2026-09-24, la app lo genera al empezar cada
     *    entrenamiento y lo manda en cada logSets()) en entrenamientos
     *    sueltos sin asignación: separa dos sesiones sueltas del mismo
     *    ejercicio el mismo día (mañana y tarde), que antes se fundían en
     *    una sola y se perdía la primera.
     * 3. Sin ninguna de las dos (filas históricas / versiones viejas de la
     *    app): el día (performed_date), como siempre.
     * workout_template_exercise_id separa un mismo ejercicio que aparece
     * dos veces en la plantilla.
     *
     * La clave se construye con CASE (sqlite y MySQL/MariaDB lo soportan
     * igual, y con ONLY_FULL_GROUP_BY no hay problema porque solo se
     * selecciona MAX(id)): cada nivel vale NULL cuando manda uno anterior,
     * así que solo "cuenta" el criterio que aplica a cada fila.
     *
     * Una foto con logged_sets = [] (el cliente desmarcó TODAS las series,
     * ver ClientCalendarController::logSets) es un estado final válido: 0
     * series. Quien sume series ya lo trata bien (foreach sobre []); quien
     * busque "la última vez que hizo este ejercicio" debe descartar esas
     * fotos vacías DESPUÉS de aplicar este scope (ver hasSets()).
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
                ->groupByRaw('CASE WHEN program_day_assignment_id IS NULL THEN session_key END')
                ->groupByRaw('CASE WHEN program_day_assignment_id IS NULL AND session_key IS NULL THEN performed_date END');
        });
    }

    /**
     * true si esta foto tiene al menos una serie. Las fotos vacías (todas
     * las series desmarcadas) cuentan como 0 series pero no son "la última
     * vez que hizo el ejercicio" para mostrar como referencia.
     */
    public function hasSets(): bool
    {
        return is_array($this->logged_sets) && count($this->logged_sets) > 0;
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
