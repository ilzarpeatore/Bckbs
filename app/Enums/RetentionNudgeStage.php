<?php

namespace App\Enums;

// Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md §8.2).
enum RetentionNudgeStage: string
{
    case DIA_7 = 'dia_7';
    case DIA_14 = 'dia_14';
    case DIA_20 = 'dia_20';

    public function minDiasInactividad(): int
    {
        return match ($this) {
            self::DIA_7 => 7,
            self::DIA_14 => 14,
            self::DIA_20 => 20,
        };
    }
}
