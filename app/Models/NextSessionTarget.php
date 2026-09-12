<?php

namespace App\Models;

use App\Enums\TargetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NextSessionTarget extends Model
{
    use HasFactory;

    /**
     * Motor de Auto-Regulación de Carga — días durante los que una
     * sugerencia ya resuelta ('aplicado') se sigue considerando relevante
     * para mostrarse (calendario del cliente, visualizador de sesión del
     * admin). Pasada la ventana deja de destacarse, pero la fila no se
     * borra. Las 'pendiente' son relevantes siempre, sin ventana, hasta
     * que se resuelven. Constante única para que las distintas superficies
     * que la consultan (ClientCalendarController, SessionDetailController)
     * no diverjan -- ver scopeRelevantForClient().
     */
    const VISIBLE_DAYS = 14;

    protected $fillable = [
        'client_id', 'exercise_id', 'workout_session_review_id', 'rule_id', 'proposed_weight', 'proposed_reps',
        // proposed_exercise_id: Fase 3 (documento §3.1) — propuesta de
        // sustitución de ejercicio (acción sustituir_ejercicio), ver
        // migración 2026_08_11_150002.
        'proposed_exercise_id',
        'status', 'generated_at', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'status'          => TargetStatus::class,
        'proposed_weight'  => 'float',
        'proposed_reps'    => 'integer',
        'generated_at'     => 'datetime',
        'resolved_at'      => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class, 'exercise_id', 'id');
    }

    public function proposedExercise()
    {
        return $this->belongsTo(Exercise::class, 'proposed_exercise_id', 'id');
    }

    public function rule()
    {
        return $this->belongsTo(SessionProgressionRule::class, 'rule_id', 'id');
    }

    public function workoutSessionReview()
    {
        return $this->belongsTo(WorkoutSessionReview::class, 'workout_session_review_id', 'id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by', 'id');
    }

    public function overrideLog()
    {
        return $this->hasOne(OverrideLog::class, 'next_session_target_id', 'id');
    }

    /**
     * Scope local: sugerencias de un cliente relevantes para mostrar en UI
     * ahora mismo -- 'pendiente' (esperando aprobación del coach) o
     * 'aplicado' dentro de VISIBLE_DAYS. Se llama como
     * NextSessionTarget::relevantForClient($clientId). Única fuente de
     * verdad de este criterio -- antes vivía duplicado en
     * ClientCalendarController; ahora también lo usa SessionDetailController
     * para que el coach vea exactamente la misma sugerencia que el cliente.
     */
    public function scopeRelevantForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId)
            ->where(function ($q) {
                $q->where('status', TargetStatus::PENDIENTE->value)
                    ->orWhere(function ($q2) {
                        $q2->where('status', TargetStatus::APLICADO->value)
                            ->where('resolved_at', '>=', now()->subDays(self::VISIBLE_DAYS));
                    });
            });
    }
}
