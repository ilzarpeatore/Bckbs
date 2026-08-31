<?php

namespace App\Services;

use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Models\AdaptiveWeekPlan;
use App\Models\ClientExerciseOverride;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Motor de Auto-Regulación de Carga — Fase 4, modo vida real (documento
 * §4.2). Genera SIEMPRE una propuesta (`status = propuesto`), nunca aplica
 * nada al calendario real del cliente — el único camino a `aprobado` es
 * `POST /api/adaptive-week-plans/{id}/approve` (coach), y la transición
 * `aprobado -> aplicado` (que escribiría los `ClientExerciseOverride`
 * reales) está fuera del alcance de esta tarea: no se pidió ningún endpoint
 * para ella y no existe todavía en el documento/plan un disparador
 * explícito. La propuesta calculada se guarda en `adaptive_week_plans.details`
 * (JSON) para que ese futuro paso pueda aplicarla sin volver a calcular
 * (evita que un cambio de programa entre "aprobado" y "aplicado" cambie
 * silenciosamente qué se aplica).
 *
 * Reutiliza `ClientExerciseOverride` solo como modelo de referencia para la
 * FORMA del resultado (program_day_assignment_id + workout_template_exercise_id),
 * tal como especifica el documento — no escribe filas en esa tabla aquí.
 *
 * DECISIÓN DE DISEÑO (el documento no define "ejercicio principal" a nivel
 * de esquema): no existe ningún campo `es_principal`/`categoria` en
 * `workout_template_exercises` ni en `exercises`. Se usa `sequence` dentro
 * de cada `workout_template_block` como proxy: el primer ejercicio
 * (sequence más bajo) de cada bloque es el "principal" de ese bloque, el
 * resto son accesorios recortables. Es el proxy más defendible disponible
 * en el esquema actual (los bloques ya agrupan ejercicios por función/
 * sección del entrenamiento, y dentro de un bloque el orden de creación
 * refleja la importancia relativa que le dio el coach).
 *
 * DECISIÓN DE DISEÑO (mantener_grupo_muscular_prioritario): tampoco existe
 * un campo de "grupo muscular prioritario" configurado por cliente/coach.
 * Se infiere el grupo prioritario de la propia semana: el bodypart_id que
 * aparece con más frecuencia entre los ejercicios de las sesiones de esa
 * semana, y se priorizan para mantener las sesiones que más ejercicios
 * tienen de ese bodypart. Documentado explícitamente aquí y en el resumen
 * final para que el coach pueda pedir un campo de configuración explícito
 * más adelante si este criterio no es suficiente.
 */
class AdaptiveWeekPlanner
{
    public function generateProposal(
        User $client,
        Carbon $originalWeekStart,
        int $sessionsAvailable,
        string $priorizacion
    ): AdaptiveWeekPlan {
        if (!Gate::forUser($client)->allows('paid-tier')) {
            throw new \RuntimeException('AdaptiveWeekPlanner: cliente free, no se generan propuestas (Fase 0).');
        }

        $weekStart = $originalWeekStart->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $sessionsThisWeek = $this->resolveSessionsForWeek($client, $weekStart, $weekEnd);
        $totalSessions = $sessionsThisWeek->count();

        if ($totalSessions === 0 || $totalSessions <= $sessionsAvailable) {
            // Nada que recortar: o no hay sesiones programadas esa semana, o
            // el cliente puede con todas las que ya tiene. Se registra la
            // propuesta igual (trazabilidad), marcando explícitamente que no
            // hace falta reducción -- nunca un comportamiento por defecto
            // silencioso (criterio de aceptación §4.3).
            $details = [
                'reduction_needed' => false,
                'sessions_total'   => $totalSessions,
                'sessions_kept'    => $sessionsThisWeek->pluck('assignment.id')->values()->all(),
                'sessions_dropped' => [],
                'accessory_trims'  => [],
            ];

            return $this->persist($client, $weekStart, $sessionsAvailable, $priorizacion, $details);
        }

        [$kept, $dropped] = $this->selectSessionsToKeep($sessionsThisWeek, $sessionsAvailable, $priorizacion);

        $accessoryTrims = [];
        if ($priorizacion === 'mantener_ejercicios_principales') {
            foreach ($kept as $item) {
                $trimIds = $this->accessoryExerciseIdsToTrim($item['assignment']);
                if (!empty($trimIds)) {
                    $accessoryTrims[$item['assignment']->id] = $trimIds;
                }
            }
        }

        $details = [
            'reduction_needed' => true,
            'sessions_total'   => $totalSessions,
            'sessions_kept'    => $kept->pluck('assignment.id')->values()->all(),
            'sessions_dropped' => $dropped->pluck('assignment.id')->values()->all(),
            'accessory_trims'  => $accessoryTrims, // program_day_assignment_id => [workout_template_exercise_id, ...]
        ];

        return $this->persist($client, $weekStart, $sessionsAvailable, $priorizacion, $details);
    }

    /**
     * Trigger del cliente (2026-08-12): a diferencia de generateProposal()
     * (el coach da un número de sesiones + una estrategia y el algoritmo
     * decide cuáles recortar), aquí el cliente ya decidió exactamente qué
     * program_day_assignments no puede hacer esa semana — no hace falta
     * ningún algoritmo de priorización, `kept`/`dropped` se separan por
     * pertenencia directa a la selección. Reutiliza resolveSessionsForWeek()
     * y persist() tal cual, sin duplicar el pipeline de Panel de Excepciones
     * / aprobación / aplicación.
     */
    public function generateFromClientSelection(User $client, Carbon $weekStart, array $unavailableAssignmentIds): AdaptiveWeekPlan
    {
        if (!Gate::forUser($client)->allows('paid-tier')) {
            throw new \RuntimeException('AdaptiveWeekPlanner: cliente free, no se generan propuestas (Fase 0).');
        }

        $weekStartAligned = $weekStart->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStartAligned->copy()->endOfWeek(Carbon::SUNDAY);

        $sessionsThisWeek = $this->resolveSessionsForWeek($client, $weekStartAligned, $weekEnd);
        $unavailableIds = array_map('intval', $unavailableAssignmentIds);

        $dropped = $sessionsThisWeek->filter(fn ($item) => in_array($item['assignment']->id, $unavailableIds, true))->values();
        $kept = $sessionsThisWeek->reject(fn ($item) => in_array($item['assignment']->id, $unavailableIds, true))->values();

        $details = [
            'reduction_needed' => $dropped->isNotEmpty(),
            'sessions_total'   => $sessionsThisWeek->count(),
            'sessions_kept'    => $kept->pluck('assignment.id')->values()->all(),
            'sessions_dropped' => $dropped->pluck('assignment.id')->values()->all(),
            'accessory_trims'  => [], // selección manual del cliente -> todo o nada por día, sin recorte de accesorios.
        ];

        return $this->persist($client, $weekStartAligned, $kept->count(), 'seleccion_manual_cliente', $details);
    }

    /**
     * Cierra la transición aprobado -> aplicado (documentada como pendiente
     * desde la instalación de Fase 4): escribe de verdad los
     * ClientExerciseOverride.hidden=true para las sesiones descartadas
     * completas y para los accesorios recortados de las sesiones
     * mantenidas. Idempotente (misma clave única de la tabla) -- llamarlo
     * dos veces sobre el mismo plan no duplica nada.
     */
    public function applyPlan(AdaptiveWeekPlan $plan): void
    {
        $details = $plan->details ?? [];

        foreach ((array) ($details['sessions_dropped'] ?? []) as $assignmentId) {
            $assignment = ProgramDayAssignment::with('workoutTemplate.blocks.exercises')->find($assignmentId);
            if (!$assignment || !$assignment->workoutTemplate) {
                continue;
            }
            foreach ($this->allExerciseIdsForAssignment($assignment) as $wteId) {
                ClientExerciseOverride::updateOrCreate(
                    ['program_day_assignment_id' => $assignmentId, 'client_id' => $plan->client_id, 'workout_template_exercise_id' => $wteId],
                    ['hidden' => true]
                );
            }
        }

        foreach ((array) ($details['accessory_trims'] ?? []) as $assignmentId => $wteIds) {
            foreach ((array) $wteIds as $wteId) {
                ClientExerciseOverride::updateOrCreate(
                    ['program_day_assignment_id' => (int) $assignmentId, 'client_id' => $plan->client_id, 'workout_template_exercise_id' => (int) $wteId],
                    ['hidden' => true]
                );
            }
        }
    }

    private function allExerciseIdsForAssignment(ProgramDayAssignment $assignment): array
    {
        $ids = [];
        foreach ($assignment->workoutTemplate->blocks as $block) {
            foreach ($block->exercises as $wte) {
                $ids[] = $wte->id;
            }
        }
        return $ids;
    }

    private function persist(
        User $client,
        Carbon $weekStart,
        int $sessionsAvailable,
        string $priorizacion,
        array $details
    ): AdaptiveWeekPlan {
        $plan = AdaptiveWeekPlan::create([
            'client_id'            => $client->id,
            'original_week_start'  => $weekStart->toDateString(),
            'sessions_available'   => $sessionsAvailable,
            'priorizacion'         => $priorizacion,
            // SIEMPRE false -- no configurable por el coach (ver docblock
            // de clase y AdaptiveWeekPlanController::generate()): una
            // semana de modo vida real nunca extiende el mesociclo, cuenta
            // como una semana normal. fecha_fin de la asignación se calcula
            // una única vez al asignar/renovar y no se toca aquí.
            'mesocycle_extension'  => false,
            'status'               => 'propuesto', // SIEMPRE -- nunca otro valor desde este servicio
            'details'              => $details,
        ]);

        // Panel de Excepciones del Coach (documento §3.5) -- un ítem por
        // cada propuesta nueva, se resuelve automáticamente al aprobar
        // (ver AdaptiveWeekPlanController::approve()).
        if ($client->coach_id) {
            (new CoachExceptionFeedService())->createOrSkip(
                coachId: (int) $client->coach_id,
                clientId: $client->id,
                category: ExceptionCategory::SEMANA_ADAPTATIVA_PENDIENTE,
                severity: ExceptionSeverity::MEDIA,
                sourceType: AdaptiveWeekPlan::class,
                sourceId: $plan->id,
                title: 'Semana adaptativa pendiente de aprobar',
                description: "Semana del {$weekStart->toDateString()}, {$sessionsAvailable} sesión(es) disponible(s)."
            );
        }

        return $plan;
    }

    /**
     * Resuelve todas las sesiones (program_day_assignments con
     * workout_template_id no nulo, i.e. no descanso) que caen dentro de la
     * semana [weekStart, weekEnd] para cualquiera de los programas activos
     * del cliente, usando el mismo CalendarDateMapper que ya usa
     * ClientCalendarController::getMyMonth() para no divergir del
     * calendario real que ve el cliente.
     */
    private function resolveSessionsForWeek(User $client, Carbon $weekStart, Carbon $weekEnd): Collection
    {
        $mapper = new CalendarDateMapper();
        $result = collect();

        $clientAssignments = ProgramClientAssignment::where('client_id', $client->id)
            ->where('activo', true)
            ->with('trainingProgram')
            ->get();

        foreach ($clientAssignments as $ca) {
            $program = $ca->trainingProgram;
            if (!$program) {
                continue;
            }

            $startDate = Carbon::parse($ca->start_date);
            $cursor = $weekStart->copy();

            while ($cursor->lte($weekEnd)) {
                $wd = $mapper->toWeekAndDay($startDate, $cursor);

                if ($wd['week_number'] >= 1 && $wd['week_number'] <= $program->num_weeks) {
                    $pda = ProgramDayAssignment::where('training_program_id', $program->id)
                        ->where('week_number', $wd['week_number'])
                        ->where('day_of_week', $wd['day_of_week'])
                        ->whereNotNull('workout_template_id')
                        ->with('workoutTemplate.blocks.exercises.exercise')
                        ->first();

                    if ($pda) {
                        $result->push(['date' => $cursor->toDateString(), 'assignment' => $pda]);
                    }
                }

                $cursor->addDay();
            }
        }

        return $result->sortBy('date')->values();
    }

    /**
     * Devuelve [kept, dropped] (ambas colecciones del mismo shape que
     * resolveSessionsForWeek). La estrategia de selección depende de
     * `priorizacion`:
     * - mantener_distribucion_semanal_completa / mantener_ejercicios_principales:
     *   muestreo uniforme a lo largo de la semana (preserva el espaciado de
     *   descansos) -- difieren solo en si se recortan accesorios dentro de
     *   las sesiones mantenidas (eso se decide en el caller).
     * - mantener_grupo_muscular_prioritario: se priorizan las sesiones con
     *   más ejercicios del bodypart más frecuente de la semana (ver
     *   docblock de clase).
     */
    private function selectSessionsToKeep(Collection $sessions, int $keepCount, string $priorizacion): array
    {
        if ($priorizacion === 'mantener_grupo_muscular_prioritario') {
            $priorityBodypartId = $this->dominantBodypartId($sessions);
            $ranked = $sessions
                ->map(function ($item) use ($priorityBodypartId) {
                    $item['score'] = $priorityBodypartId === null
                        ? 0
                        : $this->countExercisesForBodypart($item['assignment'], $priorityBodypartId);
                    return $item;
                })
                ->sortByDesc('score')
                ->values();

            $kept = $ranked->take($keepCount)->sortBy('date')->values();
            $keptIds = $kept->pluck('assignment.id')->all();
            $dropped = $sessions->reject(fn ($item) => in_array($item['assignment']->id, $keptIds, true))->values();

            return [$kept, $dropped];
        }

        // mantener_distribucion_semanal_completa | mantener_ejercicios_principales
        // -> muestreo uniforme por índice para preservar el espaciado real.
        $total = $sessions->count();
        $step = $total / $keepCount;
        $keptIndexes = [];
        for ($i = 0; $i < $keepCount; $i++) {
            $idx = (int) round($i * $step);
            $idx = min($idx, $total - 1);
            $keptIndexes[$idx] = true;
        }

        // Si el redondeo colapsó índices (keepCount cercano a total),
        // rellenar con los siguientes índices libres para respetar
        // exactamente sessions_available.
        $idx = 0;
        while (count($keptIndexes) < $keepCount && $idx < $total) {
            if (!isset($keptIndexes[$idx])) {
                $keptIndexes[$idx] = true;
            }
            $idx++;
        }

        $kept = $sessions->filter(fn ($item, $i) => isset($keptIndexes[$i]))->values();
        $dropped = $sessions->filter(fn ($item, $i) => !isset($keptIndexes[$i]))->values();

        return [$kept, $dropped];
    }

    /**
     * Bodypart_id más frecuente entre todos los ejercicios de todas las
     * sesiones de la semana. Null si ningún ejercicio tiene bodypart_ids
     * asignado.
     */
    private function dominantBodypartId(Collection $sessions): ?int
    {
        $counts = [];

        foreach ($sessions as $item) {
            foreach ($item['assignment']->workoutTemplate->blocks as $block) {
                foreach ($block->exercises as $wte) {
                    $bodypartIds = $wte->exercise->bodypart_ids ?? [];
                    foreach ((array) $bodypartIds as $bpId) {
                        $counts[$bpId] = ($counts[$bpId] ?? 0) + 1;
                    }
                }
            }
        }

        if (empty($counts)) {
            return null;
        }

        arsort($counts);
        return (int) array_key_first($counts);
    }

    private function countExercisesForBodypart(ProgramDayAssignment $assignment, int $bodypartId): int
    {
        $count = 0;
        foreach ($assignment->workoutTemplate->blocks as $block) {
            foreach ($block->exercises as $wte) {
                $bodypartIds = (array) ($wte->exercise->bodypart_ids ?? []);
                if (in_array($bodypartId, $bodypartIds, true)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * IDs de workout_template_exercises "accesorios" (todos salvo el
     * primero por `sequence` dentro de cada bloque) de una sesión mantenida.
     */
    private function accessoryExerciseIdsToTrim(ProgramDayAssignment $assignment): array
    {
        $ids = [];
        foreach ($assignment->workoutTemplate->blocks as $block) {
            // WorkoutTemplateBlock::exercises() ya ordena por 'sequence'.
            $exercises = $block->exercises;
            foreach ($exercises->skip(1) as $wte) {
                $ids[] = $wte->id;
            }
        }
        return $ids;
    }
}
