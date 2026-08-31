<?php

namespace App\Enums;

/**
 * Motor de Auto-Regulación de Carga — Fase 3 (documento §3.2, tarea #20).
 *
 * pr_carga/pr_reps/mejora_e1rm se escriben desde ClientExerciseLogObserver
 * (capa de feed sobre personal_records, gateada a paid-tier — el propio
 * personal_records/notificación NO se toca ni se gatea, sigue igual para
 * todos los clientes).
 * racha_sesiones/hito_compliance se escriben desde
 * ClientCalendarController::finishSession() (vía job EvaluateSessionAchievements).
 * mesociclo_cerrado: se escribe desde MesocycleClosureService (comando
 * diario check:mesocycle-closures) cuando program_client_assignments.
 * fecha_fin ya pasó y la asignación no estaba cerrada todavía
 * (cerrado_at). Una fila por "ejercicio principal" con datos suficientes
 * (proxy: primer ejercicio por sequence de cada bloque, mismo criterio que
 * AdaptiveWeekPlanner), value=carga_efectiva final, previous_best=carga_
 * efectiva inicial, source_type=ProgramClientAssignment.
 *
 * progreso_sesion: tipo AÑADIDO por decisión propia (no está en el
 * documento original) para la evidencia de sesión-vs-sesión-anterior de
 * WorkoutSessionStatsService::computeAchievements() — deliberadamente
 * distinto de pr_carga/mejora_e1rm (que son récords ALL-TIME vía
 * personal_records) porque compara solo contra la sesión inmediatamente
 * anterior, una señal más laxa y frecuente que sí merece persistirse
 * (documento §3.2/reconciliación: "persistir en achievement_events en vez
 * de dejarlo solo en la response transitoria").
 */
enum AchievementEventType: string
{
    case PR_CARGA = 'pr_carga';
    case PR_REPS = 'pr_reps';
    case RACHA_SESIONES = 'racha_sesiones';
    case MESOCICLO_CERRADO = 'mesociclo_cerrado';
    case MEJORA_E1RM = 'mejora_e1rm';
    case HITO_COMPLIANCE = 'hito_compliance';
    case PROGRESO_SESION = 'progreso_sesion';
}
