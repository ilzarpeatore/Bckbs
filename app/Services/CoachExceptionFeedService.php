<?php

namespace App\Services;

use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Enums\ExceptionStatus;
use App\Models\CoachExceptionItem;
use Illuminate\Database\QueryException;

/**
 * Panel de Excepciones del Coach — único punto de escritura para todos los
 * listeners por categoría (dolor, estancamiento, sugerencia_carga,
 * readiness_bajo, semana_adaptativa_pendiente, inactividad), para no
 * repetir la lógica de idempotencia en cada sitio.
 *
 * Gate de paid-tier: NO se comprueba aquí (documento, criterio de
 * aceptación §7) -- se confía en que cada punto de entrada ya lo comprueba
 * antes de generar el dato origen (next_session_targets/readiness_scores/
 * adaptive_week_plans nunca se crean para un cliente free). ÚNICA
 * EXCEPCIÓN DELIBERADA: `dolor` -- el bloqueo por dolor nunca se gatea en
 * ningún punto del Motor (es seguridad, no una feature de pago, documentado
 * dos veces ya en Fase 1: SessionInterpretationService y
 * PainReportObserver) -- suprimir el ítem de dolor de un cliente free en
 * este panel dejaría al coach sin ver en su bandeja una alerta de dolor que
 * SÍ le llegó por notificación, un resultado peor que el criterio genérico
 * del documento. Ver reconciliación en TAREAS.md.
 */
class CoachExceptionFeedService
{
    /**
     * Crea el ítem si no existe ya uno para el mismo origen
     * (source_type+source_id). Si sourceType/sourceId son null (categoría
     * `inactividad`, sin registro de origen único), el llamador es
     * responsable de comprobar `hasPendingForClientCategory()` antes de
     * llamar aquí -- este método no puede deducir idempotencia sin un
     * origen.
     */
    public function createOrSkip(
        int $coachId,
        int $clientId,
        ExceptionCategory $category,
        ExceptionSeverity $severity,
        ?string $sourceType,
        $sourceId,
        string $title,
        ?string $description = null
    ): ?CoachExceptionItem {
        $attributes = [
            'coach_id'    => $coachId,
            'client_id'   => $clientId,
            'category'    => $category->value,
            'severity'    => $severity->value,
            'source_type' => $sourceType,
            'source_id'   => $sourceId,
            'title'       => $title,
            'description' => $description,
            'status'      => ExceptionStatus::PENDIENTE->value,
        ];

        if ($sourceType === null || $sourceId === null) {
            return CoachExceptionItem::create($attributes);
        }

        try {
            $item = CoachExceptionItem::firstOrCreate(
                ['source_type' => $sourceType, 'source_id' => $sourceId],
                $attributes
            );
        } catch (QueryException $e) {
            // Carrera entre dos evaluaciones concurrentes del mismo origen
            // -- el índice unique(source_type, source_id) ya garantizó que
            // no se duplicó nada, no hay nada más que hacer aquí.
            return null;
        }

        return $item->wasRecentlyCreated ? $item : null;
    }

    /** True si ya hay un ítem `pendiente` de esta categoría para este cliente. */
    public function hasPendingForClientCategory(int $clientId, ExceptionCategory $category): bool
    {
        return CoachExceptionItem::where('client_id', $clientId)
            ->where('category', $category->value)
            ->where('status', ExceptionStatus::PENDIENTE->value)
            ->exists();
    }

    /**
     * Resuelve automáticamente (no es una acción del coach) el/los ítems
     * `pendiente` que apuntan a un origen concreto -- usado cuando el
     * coach ya actuó sobre el registro origen a través de un endpoint ya
     * existente del Motor (aprobar/editar/rechazar sugerencia, aprobar
     * semana adaptativa), para que no quede un ítem huérfano en el panel.
     */
    public function resolveBySource(string $sourceType, $sourceId, ?int $resolvedBy = null): void
    {
        CoachExceptionItem::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('status', ExceptionStatus::PENDIENTE->value)
            ->update([
                'status'      => ExceptionStatus::RESUELTA->value,
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
            ]);
    }

    /**
     * Resuelve automáticamente por cliente+categoría (no por origen
     * puntual) -- usado por readiness_bajo, cuyo origen cambia de fila cada
     * día (readiness_scores.id de HOY), así que no hay un source_id fijo
     * al que enganchar la resolución cuando el cliente vuelve a estar bien.
     */
    public function autoResolveByClientCategory(int $clientId, ExceptionCategory $category): void
    {
        CoachExceptionItem::where('client_id', $clientId)
            ->where('category', $category->value)
            ->where('status', ExceptionStatus::PENDIENTE->value)
            ->update([
                'status'      => ExceptionStatus::RESUELTA->value,
                'resolved_at' => now(),
            ]);
    }

    /**
     * Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md
     * §7) -- a diferencia de createOrSkip() (un ítem nuevo por origen), aquí
     * interesa un único ítem "vivo" por cliente+categoría, actualizado
     * in-place mientras el riesgo se mantenga medio/alto: el riesgo puede
     * subir de medio a alto de un día a otro y el coach debe verlo
     * reflejado en el mismo ítem, no en uno nuevo cada día.
     */
    public function upsertForClientCategory(
        int $coachId,
        int $clientId,
        ExceptionCategory $category,
        ExceptionSeverity $severity,
        ?string $sourceType,
        $sourceId,
        string $title,
        ?string $description = null
    ): CoachExceptionItem {
        $existing = CoachExceptionItem::where('client_id', $clientId)
            ->where('category', $category->value)
            ->where('status', ExceptionStatus::PENDIENTE->value)
            ->first();

        if ($existing) {
            $existing->update([
                'severity'    => $severity->value,
                'source_type' => $sourceType,
                'source_id'   => $sourceId,
                'title'       => $title,
                'description' => $description,
            ]);

            return $existing;
        }

        return CoachExceptionItem::create([
            'coach_id'    => $coachId,
            'client_id'   => $clientId,
            'category'    => $category->value,
            'severity'    => $severity->value,
            'source_type' => $sourceType,
            'source_id'   => $sourceId,
            'title'       => $title,
            'description' => $description,
            'status'      => ExceptionStatus::PENDIENTE->value,
        ]);
    }
}
