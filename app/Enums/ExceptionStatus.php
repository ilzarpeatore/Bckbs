<?php

namespace App\Enums;

// Panel de Excepciones del Coach (docs/Panel_Excepciones_Implementacion.md §2.2).
enum ExceptionStatus: string
{
    case PENDIENTE = 'pendiente';
    case RESUELTA = 'resuelta';
    case DESCARTADA = 'descartada';
}
