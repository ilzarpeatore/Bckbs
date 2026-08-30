<?php

namespace App\Models;

use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Enums\ExceptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Panel de Excepciones del Coach — capa de feed/índice sobre datos que el
 * Motor de Auto-Regulación ya genera. Ver docblock de la migración para las
 * desviaciones deliberadas del documento original (id/source_id bigint en
 * vez de uuid, timestamps completos).
 */
class CoachExceptionItem extends Model
{
    use HasFactory;

    protected $table = 'coach_exception_items';

    protected $fillable = [
        'coach_id', 'client_id', 'category', 'severity',
        'source_type', 'source_id', 'title', 'description',
        'status', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'category'    => ExceptionCategory::class,
        'severity'    => ExceptionSeverity::class,
        'status'      => ExceptionStatus::class,
        'resolved_at' => 'datetime',
    ];

    // AÑADIDO (Plan_Cierre_Motor_UI.md, Fase 2): title/description ya
    // resumen el ítem en texto, pero el admin necesita datos estructurados
    // para decidir sin abrir nada más (peso propuesto real, ejercicio
    // sustituto, sesiones de una semana adaptativa) -- solo para las 2
    // categorías con una acción real de aprobar/editar/rechazar detrás.
    protected $appends = ['source_detail'];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by', 'id');
    }

    /**
     * Resuelve el modelo de origen (PainReport|NextSessionTarget|
     * ReadinessScore|AdaptiveWeekPlan|null) -- misma referencia polimórfica
     * manual que AchievementEvent::source(), sin FK real.
     */
    public function source(): ?Model
    {
        if (!$this->source_type || !$this->source_id) {
            return null;
        }

        return $this->source_type::find($this->source_id);
    }

    /**
     * Detalle estructurado para la UI de admin (Plan_Cierre_Motor_UI.md,
     * Fase 2) -- solo `sugerencia_carga`/`estancamiento`
     * (NextSessionTarget, para prellenar Aprobar/Editar) y
     * `semana_adaptativa_pendiente` (AdaptiveWeekPlan, para mostrar cuántas
     * sesiones se mantienen/recortan antes de aprobar). El resto de
     * categorías ya tiene toda la info relevante en title/description.
     */
    public function getSourceDetailAttribute(): ?array
    {
        if ($this->source_type === NextSessionTarget::class) {
            /** @var NextSessionTarget|null $target */
            $target = NextSessionTarget::with(['exercise', 'proposedExercise'])->find($this->source_id);
            if (!$target) {
                return null;
            }

            return [
                'target_id'          => $target->id,
                'exercise'           => $target->exercise?->title,
                'proposed_weight'    => $target->proposed_weight,
                'proposed_reps'      => $target->proposed_reps,
                'proposed_exercise'  => $target->proposedExercise?->title,
                'status'             => $target->status->value,
            ];
        }

        if ($this->source_type === AdaptiveWeekPlan::class) {
            /** @var AdaptiveWeekPlan|null $plan */
            $plan = AdaptiveWeekPlan::find($this->source_id);
            if (!$plan) {
                return null;
            }

            $details = $plan->details ?? [];

            return [
                'plan_id'             => $plan->id,
                'original_week_start' => optional($plan->original_week_start)->toDateString(),
                'sessions_available'  => $plan->sessions_available,
                'priorizacion'        => $plan->priorizacion,
                'sessions_total'      => $details['sessions_total'] ?? null,
                'sessions_kept'       => count($details['sessions_kept'] ?? []),
                'sessions_dropped'    => count($details['sessions_dropped'] ?? []),
                'status'              => $plan->status,
            ];
        }

        return null;
    }
}
