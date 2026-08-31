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

    /**
     * documento §2.2 paso 5: "excluyendo automáticamente reglas que
     * dependan de rir_delta_sesión/peor_serie si sin_dato_suficiente=true".
     */
    public function dependsOnRirData(): bool
    {
        return match ($this) {
            self::RIR_DELTA_SESION, self::PEOR_SERIE => true,
            default => false,
        };
    }
}
