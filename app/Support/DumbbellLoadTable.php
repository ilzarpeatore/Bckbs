<?php

namespace App\Support;

/**
 * Escalonado real de una rastrillera de mancuernas comercial: 1kg hasta
 * 15kg, luego 2.5kg hasta 50kg. No es un incremento uniforme (a diferencia
 * de `Exercise::increment_kg`), así que necesita su propia función de
 * snapping en vez de un simple `round($value / step) * step`.
 */
class DumbbellLoadTable
{
    private const LOW_TIER_MAX = 15.0;
    private const LOW_TIER_STEP = 1.0;
    private const HIGH_TIER_STEP = 2.5;
    private const MIN = 1.0;
    private const MAX = 50.0;

    public static function snap(float $value): float
    {
        $value = max(self::MIN, min(self::MAX, $value));

        if ($value <= self::LOW_TIER_MAX) {
            return round($value / self::LOW_TIER_STEP) * self::LOW_TIER_STEP;
        }

        $snapped = self::LOW_TIER_MAX
            + round(($value - self::LOW_TIER_MAX) / self::HIGH_TIER_STEP) * self::HIGH_TIER_STEP;

        return min(self::MAX, $snapped);
    }
}
