<?php

namespace App\Enums;

// Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1, §2.3).
enum RuleMode: string
{
    case AUTOMATICO = 'automatico';
    case SUGERIDO_PENDIENTE_APROBACION = 'sugerido_pendiente_aprobacion';
}
