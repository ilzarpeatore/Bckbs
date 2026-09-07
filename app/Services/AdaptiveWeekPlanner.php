<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Enums\TargetStatus;
use App\Models\AdaptiveWeekPlan;
use App\Models\ClientExerciseOverride;
use App\Models\ExerciseSessionMetric;
use App\Models\NextSessionTarget;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Motor de Auto-Regulación de Carga — Fase 4, modo vida real (documento
 * §4.2). `generateProposal()`/`generateFromClientSelection()` SIEMPRE
 * generan una propuesta (`status = propuesto`), nunca aplican nada al
 * calendario real del cliente por sí solas — el único camino a `aprobado`
 * es `POST /api/adaptive-week-plans/{id}/approve` (coach). La transición
 * `aprobado -> aplicado` (que escribe los `ClientExerciseOverride` reales)
 * SÍ está implementada en esta misma clase: ver `applyPlan()` más abajo. La
 * propuesta calculada se guarda en `adaptive_week_plans.details` (JSON)
 * precisamente para que `applyPlan()` pueda aplicarla sin volver a calcular
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
    /**
     * Tope de saturación para el factor de progreso real (ítem 14, Ronda 4)
     * -- una racha/estancamiento de 3+ sesiones ya es una señal clara, no
     * hace falta distinguir 3 de 10 para decidir qué sesión recortar antes.
     */
    private const PROGRESS_STREAK_CAP = 3;

    /**
     * Nº de semanas consecutivas recortando el mismo día que dispara el
     * aviso de "patrón recurrente" al coach (ítem 15, Ronda 4).
     */
    private const RECURRING_DROP_STREAK_WEEKS = 3;

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

        [$kept, $dropped] = $this->selectSessionsToKeep($sessionsThisWeek, $sessionsAvailable, $priorizacion, $client->id);

        $accessoryTrims = [];
        if ($priorizacion === 'mantener_ejercicios_principales') {
            foreach ($kept as $item) {
                $trimIds = $this->accessoryExerciseIdsToTrim($item['assignment'], $client->id);
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

        // Ítem 15, Ronda 4: memoria entre semanas adaptativas consecutivas
        // -- antes de persistir esta propuesta, comprueba si ya viene
        // recortando el mismo día varias semanas seguidas.
        $this->detectRecurringDropPattern($client, $weekStart, $details['sessions_dropped']);

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
     *
     * Ítem 12, Ronda 4 (antes: hasta 7 queries por programa activo, una por
     * día dentro de un loop). `[weekStart, weekEnd]` son siempre los 7 días
     * de UNA misma semana de calendario (lunes a domingo), y
     * `CalendarDateMapper::toWeekAndDay()` calcula `week_number` comparando
     * el lunes de la semana de `start_date` con el lunes de la semana de la
     * fecha consultada -- como todos los días del rango comparten el mismo
     * lunes, `week_number` es CONSTANTE para los 7 días, solo `day_of_week`
     * cambia. Eso permite resolverlo una única vez por programa y traer de
     * un solo query todas las asignaciones de esa semana (sea cual sea el
     * día), en vez de repetir la query día a día.
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
            $weekNumber = $mapper->toWeekAndDay($startDate, $weekStart)['week_number'];

            if ($weekNumber < 1 || $weekNumber > $program->num_weeks) {
                continue;
            }

            $pdasByDay = ProgramDayAssignment::where('training_program_id', $program->id)
                ->where('week_number', $weekNumber)
                ->whereNotNull('workout_template_id')
                ->with('workoutTemplate.blocks.exercises.exercise')
                ->get()
                ->keyBy('day_of_week');

            $cursor = $weekStart->copy();
            while ($cursor->lte($weekEnd)) {
                $pda = $pdasByDay->get($cursor->dayOfWeekIso);
                if ($pda) {
                    $result->push(['date' => $cursor->toDateString(), 'assignment' => $pda]);
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
     * - mantener_distribucion_semanal_completa: muestreo uniforme a lo
     *   largo de la semana (preserva el espaciado de descansos), puro por
     *   fecha -- no puntúa por contenido de la sesión.
     * - mantener_ejercicios_principales: se priorizan las sesiones con más
     *   ejercicios "principales" (primero por `sequence` de cada bloque,
     *   ver docblock de clase) -- antes (bug, Ronda 4 ítem 11) caía en el
     *   mismo muestreo uniforme que la opción anterior, así que elegir esta
     *   estrategia no cambiaba nada salvo el recorte de accesorios
     *   posterior.
     * - mantener_grupo_muscular_prioritario: se priorizan las sesiones con
     *   más ejercicios del bodypart más frecuente de la semana (ver
     *   docblock de clase).
     *
     * Ítem 14, Ronda 4: las dos estrategias "por puntuación" (principales /
     * grupo muscular) suman además `progressAdjustment()` -- un factor de
     * progreso real por sesión (ver su docblock) -- para no recortar solo
     * por fecha/frecuencia ignorando si el cliente está progresando o
     * estancado en esos ejercicios. `mantener_distribucion_semanal_completa`
     * NO lo usa a propósito: debe seguir siendo puro espaciado de fechas.
     */
    private function selectSessionsToKeep(Collection $sessions, int $keepCount, string $priorizacion, int $clientId): array
    {
        if ($priorizacion === 'mantener_grupo_muscular_prioritario') {
            $priorityBodypartId = $this->dominantBodypartId($sessions);
            $ranked = $sessions
                ->map(function ($item) use ($priorityBodypartId, $clientId) {
                    $bodypartScore = $priorityBodypartId === null
                        ? 0
                        : $this->countExercisesForBodypart($item['assignment'], $priorityBodypartId);
                    $item['score'] = $bodypartScore + $this->progressAdjustment($item['assignment'], $clientId);
                    return $item;
                })
                ->sortByDesc('score')
                ->values();

            $kept = $ranked->take($keepCount)->sortBy('date')->values();
            $keptIds = $kept->pluck('assignment.id')->all();
            $dropped = $sessions->reject(fn ($item) => in_array($item['assignment']->id, $keptIds, true))->values();

            return [$kept, $dropped];
        }

        if ($priorizacion === 'mantener_ejercicios_principales') {
            $ranked = $sessions
                ->map(function ($item) use ($clientId) {
                    $item['score'] = $this->countPrincipalExercises($item['assignment'])
                        + $this->progressAdjustment($item['assignment'], $clientId);
                    return $item;
                })
                ->sortByDesc('score')
                ->values();

            $kept = $ranked->take($keepCount)->sortBy('date')->values();
            $keptIds = $kept->pluck('assignment.id')->all();
            $dropped = $sessions->reject(fn ($item) => in_array($item['assignment']->id, $keptIds, true))->values();

            return [$kept, $dropped];
        }

        // mantener_distribucion_semanal_completa -> muestreo uniforme por
        // índice para preservar el espaciado real, sin puntuar contenido.
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
     * Nº de ejercicios "principales" (el primero por `sequence` de cada
     * bloque, ver docblock de clase) de una sesión -- mismo patrón de
     * scoring que `countExercisesForBodypart()`, usado por
     * `mantener_ejercicios_principales` (Ronda 4, ítem 11) para priorizar
     * de verdad las sesiones con más ejercicios compuestos/principales, en
     * vez de caer en el muestreo uniforme genérico.
     */
    private function countPrincipalExercises(ProgramDayAssignment $assignment): int
    {
        $count = 0;
        foreach ($assignment->workoutTemplate->blocks as $block) {
            if ($block->exercises->isNotEmpty()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * IDs de workout_template_exercises "accesorios" (todos salvo el
     * primero por `sequence` dentro de cada bloque) de una sesión mantenida,
     * EXCLUYENDO los que el coach marcó explícitamente como
     * `no_recortable` en `ClientExerciseOverride` (Ronda 4, ítem 13 --
     * p. ej. trabajo de rehabilitación prescrito por dolor, que nunca debe
     * proponerse para recorte aunque no sea el "principal" del bloque).
     */
    private function accessoryExerciseIdsToTrim(ProgramDayAssignment $assignment, int $clientId): array
    {
        $ids = [];
        foreach ($assignment->workoutTemplate->blocks as $block) {
            // WorkoutTemplateBlock::exercises() ya ordena por 'sequence'.
            $exercises = $block->exercises;
            foreach ($exercises->skip(1) as $wte) {
                $ids[] = $wte->id;
            }
        }

        if (empty($ids)) {
            return $ids;
        }

        $noRecortableIds = ClientExerciseOverride::where('program_day_assignment_id', $assignment->id)
            ->where('client_id', $clientId)
            ->where('no_recortable', true)
            ->whereIn('workout_template_exercise_id', $ids)
            ->pluck('workout_template_exercise_id')
            ->all();

        if (empty($noRecortableIds)) {
            return $ids;
        }

        return array_values(array_diff($ids, $noRecortableIds));
    }

    /**
     * Ítem 14, Ronda 4 -- factor de progreso real de una sesión, sumado al
     * score de `mantener_ejercicios_principales` /
     * `mantener_grupo_muscular_prioritario` (NUNCA a
     * `mantener_distribucion_semanal_completa`, que debe seguir siendo
     * puro espaciado de fechas). Suma el `exerciseProgressScore()` de cada
     * ejercicio de la sesión: una sesión donde el cliente progresa de
     * verdad puntúa más alto (más candidata a MANTENER), una sesión
     * estancada puntúa más bajo (más candidata a RECORTAR). Es una
     * heurística nueva, no una fórmula del documento original -- ver el
     * criterio de peso en `exerciseProgressScore()`.
     */
    private function progressAdjustment(ProgramDayAssignment $assignment, int $clientId): float
    {
        $score = 0.0;
        foreach ($assignment->workoutTemplate->blocks as $block) {
            foreach ($block->exercises as $wte) {
                $score += $this->exerciseProgressScore($clientId, $wte->exercise_id);
            }
        }
        return $score;
    }

    /**
     * Progreso reciente de UN ejercicio para este cliente, a partir de la
     * última métrica válida (`is_outlier = false`) en `exercise_session_metrics`:
     * - Progresando (+): `racha_misma_direccion` > 0 (Fase 2,
     *   SessionProgressionRuleEngine::updateRachaMismaDireccion -- racha de
     *   sesiones seguidas con la misma dirección de acción) Y la propuesta
     *   más reciente (pendiente o ya aplicada) para ese ejercicio es una
     *   subida real de carga/reps (`hasRecentUpwardTarget()`) -- así una
     *   racha de "mantener"/"bloquear" (dirección `hold`, no sube nada) no
     *   cuenta como progreso. Suma hasta `PROGRESS_STREAK_CAP` puntos.
     * - Estancado (-): `sesiones_consecutivas_sin_cambio` > 0 (misma
     *   `exercise_session_metrics`, Fase 1) resta hasta `PROGRESS_STREAK_CAP`
     *   puntos -- cuantas más sesiones seguidas sin cambio de carga, más
     *   candidato a recortar.
     * Sin métrica todavía (ejercicio nuevo/sin histórico) -> 0, neutro.
     * No es una fórmula exacta (el propio ítem 14 no la pide perfecta,
     * solo razonable y legible) -- es deliberadamente simétrica y acotada
     * para no dominar por completo el score principal (nº de ejercicios
     * principales / del bodypart prioritario).
     */
    private function exerciseProgressScore(int $clientId, int $exerciseId): float
    {
        $metric = ExerciseSessionMetric::where('client_id', $clientId)
            ->where('exercise_id', $exerciseId)
            ->where('is_outlier', false)
            ->latest('id')
            ->first();

        if (!$metric) {
            return 0.0;
        }

        $score = 0.0;

        if ($metric->racha_misma_direccion > 0 && $this->hasRecentUpwardTarget($clientId, $exerciseId)) {
            $score += min($metric->racha_misma_direccion, self::PROGRESS_STREAK_CAP);
        }

        if ($metric->sesiones_consecutivas_sin_cambio > 0) {
            $score -= min($metric->sesiones_consecutivas_sin_cambio, self::PROGRESS_STREAK_CAP);
        }

        return $score;
    }

    /**
     * True si la propuesta (NextSessionTarget) más reciente pendiente o ya
     * aplicada para este cliente+ejercicio es una subida real de carga/reps
     * (mismo criterio de "dirección" que
     * SessionProgressionRuleEngine::directionOfRule(), simplificado aquí a
     * solo el caso "up" porque es el único que interesa a este heurístico).
     */
    private function hasRecentUpwardTarget(int $clientId, int $exerciseId): bool
    {
        $target = NextSessionTarget::where('client_id', $clientId)
            ->where('exercise_id', $exerciseId)
            ->whereIn('status', [TargetStatus::PENDIENTE->value, TargetStatus::APLICADO->value])
            ->with('rule.action')
            ->latest('generated_at')
            ->first();

        $action = $target?->rule?->action;
        if (!$action) {
            return false;
        }

        return in_array($action->type, [ActionType::AJUSTAR_CARGA_PCT, ActionType::AJUSTAR_CARGA_ABSOLUTA, ActionType::AJUSTAR_REPS], true)
            && $action->value !== null
            && (float) $action->value > 0;
    }

    /**
     * Ítem 15, Ronda 4 -- memoria entre semanas adaptativas consecutivas.
     * Si el mismo día de la semana (`ProgramDayAssignment.day_of_week`,
     * 1=lunes..7=domingo -- estable entre semanas, a diferencia de
     * `program_day_assignment_id`, que cambia cada semana) aparece en
     * `sessions_dropped` durante `RECURRING_DROP_STREAK_WEEKS` (3) semanas
     * de calendario SEGUIDAS (una semana sin propuesta algorítmica, o con
     * `sessions_dropped` vacío, o una semana de `seleccion_manual_cliente`
     * -- que es una decisión del cliente, no del algoritmo -- rompe la
     * racha), se crea un ítem en el panel de excepciones del coach: es una
     * señal de que el problema es el día del programa base, no una
     * excepción puntual de esa semana. Idempotente vía
     * `hasPendingForClientCategory()` -- no se duplica cada semana mientras
     * el ítem anterior siga sin resolver.
     */
    private function detectRecurringDropPattern(User $client, Carbon $weekStart, array $droppedAssignmentIds): void
    {
        if (empty($droppedAssignmentIds) || !$client->coach_id) {
            return;
        }

        $streakDays = ProgramDayAssignment::whereIn('id', $droppedAssignmentIds)
            ->pluck('day_of_week')
            ->unique()
            ->all();

        if (empty($streakDays)) {
            return;
        }

        $cursorWeekStart = $weekStart->copy();

        for ($i = 1; $i < self::RECURRING_DROP_STREAK_WEEKS; $i++) {
            $cursorWeekStart = $cursorWeekStart->copy()->subWeek();

            $priorPlan = AdaptiveWeekPlan::where('client_id', $client->id)
                ->whereDate('original_week_start', $cursorWeekStart->toDateString())
                ->where('priorizacion', '!=', 'seleccion_manual_cliente')
                ->latest('id')
                ->first();

            if (!$priorPlan) {
                return; // racha rota: no hubo propuesta algorítmica esa semana.
            }

            $priorDroppedIds = (array) ($priorPlan->details['sessions_dropped'] ?? []);
            if (empty($priorDroppedIds)) {
                return; // esa semana no hizo falta recortar nada.
            }

            $priorDays = ProgramDayAssignment::whereIn('id', $priorDroppedIds)->pluck('day_of_week')->unique()->all();
            $streakDays = array_intersect($streakDays, $priorDays);

            if (empty($streakDays)) {
                return; // ningún día en común con la semana anterior: racha rota.
            }
        }

        $feed = new CoachExceptionFeedService();
        if ($feed->hasPendingForClientCategory($client->id, ExceptionCategory::PATRON_RECORTE_RECURRENTE)) {
            return;
        }

        $dayNames = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
        $dayLabel = implode(', ', array_map(fn ($d) => $dayNames[$d] ?? (string) $d, $streakDays));

        $feed->createOrSkip(
            coachId: (int) $client->coach_id,
            clientId: $client->id,
            category: ExceptionCategory::PATRON_RECORTE_RECURRENTE,
            severity: ExceptionSeverity::MEDIA,
            sourceType: null,
            sourceId: null,
            title: 'Recorte recurrente del mismo día en semanas adaptativas',
            description: self::RECURRING_DROP_STREAK_WEEKS . " semanas seguidas recortando el {$dayLabel} -- revisa si el programa base debería cambiar ese día."
        );
    }
}
