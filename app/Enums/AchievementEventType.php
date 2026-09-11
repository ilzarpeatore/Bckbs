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
    // Plan de Optimización, Ronda 12 ítem 35 (docs/Motor_Autorregulacion_Analisis.md):
    // supera el mejor valor de los últimos RECENT_BEST_WINDOW_DAYS (90) sin
    // llegar a ser récord ALL-TIME -- reconoce progreso real durante una
    // recuperación (lesión, parón) sin esperar a superar un pico de hace
    // años. Ver ClientExerciseLogObserver::maybeRecordRecentBest().
    case MEJOR_MARCA_RECIENTE = 'mejor_marca_reciente';
    // Ítem 36: cliente en fase de pérdida de grasa/recomposición
    // (TrainingQuestionnaireAnswer.goal_type) que mantiene su fuerza sin
    // bajar -- logro distinto de un PR al alza, orientado a reforzar
    // adherencia durante un déficit calórico.
    case MANTIENE_FUERZA_EN_DEFICIT = 'mantiene_fuerza_en_deficit';
}
