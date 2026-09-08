<?php

namespace App\Enums;

/**
 * Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1).
 * Las últimas 3 (readiness_band, hrv_z_score, sueno_z_score) se resuelven
 * contra `readiness_scores` (Fase 4), ver
 * SessionProgressionRuleEngine::resolveReadinessValue() -- sin dato para
 * la fecha de la sesión, la condición falla como cualquier otra variable
 * sin dato (mismo criterio del resto del motor). El gate isPhase4Only()
 * que las forzaba a "nunca cumplida" mientras Fase 4 no existía se
 * eliminó, ya no hace falta (readiness_scores existe desde el mismo día
 * que se construyó el resto del motor).
 */
enum ConditionVariable: string
{
    case RIR_DELTA_SESION = 'rir_delta_sesion';
    case COMPLETION_RATIO = 'completion_ratio';
    case TENDENCIA_RIR = 'tendencia_rir';
    case SESIONES_CONSECUTIVAS_SIN_CAMBIO = 'sesiones_consecutivas_sin_cambio';
    case PEOR_SERIE = 'peor_serie';
    case SIN_DATO_SUFICIENTE = 'sin_dato_suficiente';
    case E1RM_DELTA = 'e1rm_delta';
    case READINESS_BAND = 'readiness_band';
    case HRV_Z_SCORE = 'hrv_z_score';
    case SUENO_Z_SCORE = 'sueno_z_score';
    // Plan de Optimización, Ronda 7 ítem 25 (docs/Motor_Autorregulacion_Analisis.md):
    // meses de experiencia real de entrenamiento -- independiente del nº de
    // sesiones registradas en la app (un cliente puede llevar años
    // entrenando fuera de la plataforma). Ver
    // SessionProgressionRuleEngine::resolveNivelExperiencia() para la
    // prioridad override-del-coach > autoevaluado > sin dato.
    case NIVEL_EXPERIENCIA = 'nivel_experiencia';
    // Plan de Optimización, Ronda 9 ítem 27: pendiente lineal de
    // volumen_total (tonelaje real) en las últimas TREND_WINDOW sesiones
    // válidas -- mismo patrón que TENDENCIA_RIR, calculada en Fase 1
    // (SessionInterpretationService::updateTrendMetrics()) y ya persistida
    // en exercise_session_metrics.tendencia_volumen, sin resolución
    // adicional en el motor de reglas.
    case TENDENCIA_VOLUMEN = 'tendencia_volumen';
    // Plan de Optimización, Ronda 13 (docs/Motor_Autorregulacion_Analisis.md),
    // ítems 38-42 -- refinamientos de ejecución de reglas:
    case REPS_EN_TOPE_RANGO = 'reps_en_tope_rango'; // ítem 38: doble progresión por rango de reps.
    case RIR_DELTA_SERIE_TOP = 'rir_delta_serie_top'; // ítem 39: serie top vs. backoff.
    case ROL_EJERCICIO = 'rol_ejercicio'; // ítem 41: principal (1.0) / accesorio (0.0) del bloque.
    case DOLOR_RECIENTE_NO_BLOQUEANTE = 'dolor_reciente_no_bloqueante'; // ítem 42: gradiente de precaución.
    // Plan de Optimización, Ronda 15 ítem 44: nº de bajar_carga_pct/
    // marcar_para_coach ya disparados en ESTA sesión (otros ejercicios
    // evaluados antes que este, mismo job) -- ver
    // SessionProgressionRuleEngine::$accionesBajadaEnSesion.
    case ACCIONES_BAJADA_EN_SESION = 'acciones_bajada_en_sesion';

    /**
     * documento §2.2 paso 5: "excluyendo automáticamente reglas que
     * dependan de rir_delta_sesión/peor_serie si sin_dato_suficiente=true".
     * RIR_DELTA_SERIE_TOP (ítem 39) es RIR igual que las otras dos -- mismo
     * criterio de exclusión.
     */
    public function dependsOnRirData(): bool
    {
        return match ($this) {
            self::RIR_DELTA_SESION, self::PEOR_SERIE, self::RIR_DELTA_SERIE_TOP => true,
            default => false,
        };
    }
}
