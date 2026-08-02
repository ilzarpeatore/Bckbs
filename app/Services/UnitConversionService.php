<?php

namespace App\Services;

use App\Models\MeasurementUnit;
use App\Models\Ingredient;

class UnitConversionService
{
    /**
     * Convert grams to a target unit.
     */
    public function gramsToUnit(float $grams, MeasurementUnit $unit, Ingredient $ingredient = null): float
    {
        if ($unit->unit_type === 'weight') {
            return $grams / ($unit->base_conversion_factor ?: 1);
        }

        if ($unit->unit_type === 'volume' && $ingredient) {
            $ml = $grams / ($ingredient->density ?: 1.0);
            return $ml / ($unit->base_conversion_factor ?: 1);
        }

        return $grams; // Fallback
    }

    /**
     * Convert a value from one unit to another (same type).
     */
    public function convert(float $value, MeasurementUnit $from, MeasurementUnit $to, Ingredient $ingredient = null): float
    {
        // Convert to base (grams or ml)
        $baseValue = $value * ($from->base_conversion_factor ?: 1);

        if ($from->unit_type === $to->unit_type) {
            return $baseValue / ($to->base_conversion_factor ?: 1);
        }

        // Weight to Volume conversion requires density
        if ($from->unit_type === 'weight' && $to->unit_type === 'volume' && $ingredient) {
            $ml = $baseValue / ($ingredient->density ?: 1.0);
            return $ml / ($to->base_conversion_factor ?: 1);
        }

        // Volume to Weight conversion requires density
        if ($from->unit_type === 'volume' && $to->unit_type === 'weight' && $ingredient) {
            $grams = $baseValue * ($ingredient->density ?: 1.0);
            return $grams / ($to->base_conversion_factor ?: 1);
        }

        return $baseValue;
    }

    /**
     * Format a value for display based on unit type and system preference.
     */
    public static function format(float $grams, string $unitType, string $system = 'metric', Ingredient $ingredient = null): string
    {
        if ($unitType === 'volume' && $ingredient) {
            $ml = $grams / ($ingredient->density ?: 1.0);
            
            if ($system === 'imperial') {
                if ($ml >= 240) {
                    return round($ml / 240, 2) . ' cup';
                }
                return round($ml / 29.574, 2) . ' fl oz'; // US fluid ounce
            }

            if ($ml >= 1000) {
                return round($ml / 1000, 2) . ' l';
            }
            return round($ml, 0) . ' ml';
        }

        // Default to weight display
        if ($system === 'imperial') {
            if ($grams >= 453.59) {
                return round($grams / 453.59, 2) . ' lb';
            }
            return round($grams / 28.35, 2) . ' oz';
        }

        if ($grams >= 1000) {
            return round($grams / 1000, 2) . ' kg';
        }
        return round($grams, 0) . ' g';
    }
}
