<?php

namespace App\Enums;

// Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md §4).
enum RiskBand: string
{
    case BAJO = 'bajo';
    case MEDIO = 'medio';
    case ALTO = 'alto';
    case DATO_INSUFICIENTE = 'dato_insuficiente';
}
