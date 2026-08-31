<?php

namespace App\Enums;

// Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1).
enum ConditionOperator: string
{
    case GTE = 'gte';
    case LTE = 'lte';
    case EQ = 'eq';
    case BETWEEN = 'between';
    case NO_CHANGE_FOR_N = 'no_change_for_n';
}
