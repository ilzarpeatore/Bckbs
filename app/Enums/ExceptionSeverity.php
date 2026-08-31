<?php

namespace App\Enums;

// Panel de Excepciones del Coach (docs/Panel_Excepciones_Implementacion.md §2.2).
enum ExceptionSeverity: string
{
    case ALTA = 'alta';
    case MEDIA = 'media';
    case BAJA = 'baja';
}
