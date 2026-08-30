<?php

namespace App\Enums;

// Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1).
enum RoundingMode: string
{
    case NEAREST_1KG = 'nearest_1kg';
    case NEAREST_2_5KG = 'nearest_2_5kg';
    case NONE = 'none';

    public function apply(float $value): float
    {
        return match ($this) {
            self::NEAREST_1KG => round($value),
            self::NEAREST_2_5KG => round($value / 2.5) * 2.5,
            self::NONE => $value,
        };
    }
}
