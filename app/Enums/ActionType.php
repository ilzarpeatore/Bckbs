<?php

namespace App\Enums;

// Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1).
enum ActionType: string
{
    case AJUSTAR_CARGA_PCT = 'ajustar_carga_pct';
    case AJUSTAR_CARGA_ABSOLUTA = 'ajustar_carga_absoluta';
    case AJUSTAR_REPS = 'ajustar_reps';
    case MANTENER = 'mantener';
    case BAJAR_CARGA_PCT = 'bajar_carga_pct';
    case SUSTITUIR_EJERCICIO = 'sustituir_ejercicio';
    case BLOQUEAR_PROGRESION = 'bloquear_progresion';
    case MARCAR_PARA_COACH = 'marcar_para_coach';

    /** Acciones que no proponen ni carga ni reps nuevos. */
    public function isNeutral(): bool
    {
        return match ($this) {
            self::MANTENER, self::BLOQUEAR_PROGRESION, self::MARCAR_PARA_COACH, self::SUSTITUIR_EJERCICIO => true,
            default => false,
        };
    }
}
