<?php

namespace App\Enums;

// Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1).
enum BaseReference: string
{
    case ULTIMO_PRESCRITO = 'ultimo_prescrito';
    case ULTIMO_EFECTIVO = 'ultimo_efectivo';
    case E1RM_ESTIMADO = 'e1rm_estimado';
    case PRIMERA_SEMANA_MESOCICLO = 'primera_semana_mesociclo';
}
