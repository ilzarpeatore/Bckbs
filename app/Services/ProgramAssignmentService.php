<?php

namespace App\Services;

use App\Models\ProgramClientAssignment;
use App\Models\TrainingProgram;
use Carbon\Carbon;

/**
 * Fase 2 de docs/PLAN_CLONADO_PROGRAMAS.md — punto único que reemplaza el
 * sub-patrón `existing = ProgramClientAssignment::where(...)->first();
 * $existing ? update() : create();` repetido hasta ahora en los 5 sitios
 * que asignan un TrainingProgram a un cliente (ver §1.2 del plan):
 * `TrainingProgramController::assignClient()`,
 * `ClientProfileCalendarController::importProgram()`,
 * `PackageFulfillmentService::assignTrainingProgram()`,
 * `PlanFulfillmentService::assignTrainingProgram()` y
 * `AssignProgramClientCommand`.
 *
 * Con el feature flag `services.program_cloning.enabled` DESACTIVADO
 * (default en producción, ver config/services.php), `assignOrRenew()`
 * reproduce EXACTAMENTE el comportamiento de hoy: crea/actualiza apuntando
 * directo al `training_program_id` de biblioteca, sin clonar nada. Con el
 * flag ACTIVADO, la primera asignación de un cliente a un programa de
 * biblioteca dispara `ProgramCloningService::clone()` y la asignación pasa
 * a apuntar a esa copia exclusiva; una renovación posterior del MISMO
 * programa de biblioteca para el MISMO cliente reutiliza su copia ya
 * clonada (resuelta vía `training_programs.source_training_program_id`),
 * no clona una segunda vez.
 */
class ProgramAssignmentService
{
    public function __construct(private readonly ProgramCloningService $cloningService = new ProgramCloningService())
    {
    }

    /**
     * @param array{
     *     start_date?: \Illuminate\Support\Carbon|\DateTimeInterface|string,
     *     source_subscription_id?: int|null,
     *     only_active?: bool,
     *     reset_closure?: bool,
     * } $attributes
     *
     * - `only_active` (default false): si la búsqueda de una asignación ya
     *   existente (con el flag DESACTIVADO) debe exigir `activo = true`.
     *   Reproduce la diferencia real que ya existe hoy entre callers:
     *   `importProgram()` sí lo exige, el resto no.
     * - `reset_closure` (default true): si al renovar una asignación ya
     *   existente se debe reabrir (`activo = true`, `cerrado_at = null`).
     *   `importProgram()` no lo hace hoy (su búsqueda ya exige activo=true,
     *   así que solo desplaza las fechas); el resto sí.
     */
    public function assignOrRenew(int $clientId, TrainingProgram $libraryProgram, array $attributes = []): ProgramClientAssignment
    {
        $startDate = Carbon::parse($attributes['start_date'] ?? now());
        $fechaFin = ProgramClientAssignment::computeFechaFin($startDate, $libraryProgram->num_weeks);
        $sourceSubscriptionId = $attributes['source_subscription_id'] ?? null;
        $resetClosure = (bool) ($attributes['reset_closure'] ?? true);

        if (config('services.program_cloning.enabled')) {
            $existing = ProgramClientAssignment::where('client_id', $clientId)
                ->where('activo', true)
                ->whereHas('trainingProgram', function ($query) use ($libraryProgram) {
                    $query->where('source_training_program_id', $libraryProgram->id);
                })
                ->first();

            if ($existing) {
                return $this->renew($existing, $startDate, $fechaFin, $resetClosure, $sourceSubscriptionId);
            }

            $clientCopy = $this->cloningService->clone($libraryProgram, $clientId);

            return ProgramClientAssignment::create([
                'training_program_id'    => $clientCopy->id,
                'client_id'              => $clientId,
                'start_date'             => $startDate->toDateString(),
                'fecha_fin'              => $fechaFin->toDateString(),
                'activo'                 => true,
                'source_subscription_id' => $sourceSubscriptionId,
            ]);
        }

        // Flag OFF (default) -- comportamiento actual, sin clonar: apunta
        // siempre directo a $libraryProgram->id, igual que los 5 sitios
        // hacían antes de este servicio.
        $query = ProgramClientAssignment::where('training_program_id', $libraryProgram->id)
            ->where('client_id', $clientId);

        if ($attributes['only_active'] ?? false) {
            $query->where('activo', true);
        }

        $existing = $query->first();

        if ($existing) {
            return $this->renew($existing, $startDate, $fechaFin, $resetClosure, $sourceSubscriptionId);
        }

        return ProgramClientAssignment::create([
            'training_program_id'    => $libraryProgram->id,
            'client_id'              => $clientId,
            'start_date'             => $startDate->toDateString(),
            'fecha_fin'              => $fechaFin->toDateString(),
            'activo'                 => true,
            'source_subscription_id' => $sourceSubscriptionId,
        ]);
    }

    private function renew(
        ProgramClientAssignment $assignment,
        Carbon $startDate,
        Carbon $fechaFin,
        bool $resetClosure,
        ?int $sourceSubscriptionId
    ): ProgramClientAssignment {
        $data = [
            'start_date' => $startDate->toDateString(),
            'fecha_fin'  => $fechaFin->toDateString(),
        ];

        if ($resetClosure) {
            // Renovación = nuevo ciclo del mesociclo, no continuación del
            // cerrado (mismo comentario literal que ya traían los 5 sitios
            // originales).
            $data['activo'] = true;
            $data['cerrado_at'] = null;
        }

        if ($sourceSubscriptionId !== null) {
            $data['source_subscription_id'] = $sourceSubscriptionId;
        }

        $assignment->update($data);

        return $assignment;
    }
}
