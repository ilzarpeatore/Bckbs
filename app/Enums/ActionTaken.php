<?php

namespace App\Enums;

// Motor de Auto-Regulación de Carga — Fase 2 (documento §2.3, override_logs).
enum ActionTaken: string
{
    case ACCEPTED = 'accepted';
    case EDITED = 'edited';
    case REJECTED = 'rejected';
}
