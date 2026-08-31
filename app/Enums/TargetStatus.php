<?php

namespace App\Enums;

// Motor de Auto-Regulación de Carga — Fase 2 (documento §2.2).
enum TargetStatus: string
{
    case APLICADO = 'aplicado';
    case PENDIENTE = 'pendiente';
    case RECHAZADO = 'rechazado';
}
