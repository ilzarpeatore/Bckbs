<?php

namespace App\Services\Concerns;

/**
 * Motor de Auto-Regulación de Carga — helper compartido (Plan de
 * Optimización, Ronda 3 ítem 9, ver docs/Motor_Autorregulacion_Analisis.md).
 *
 * Extrae el núcleo matemático que antes vivía duplicado de forma IDÉNTICA
 * en SessionInterpretationService::linearSlope() y
 * SessionProgressionRuleEngine::linearSlope() (este último con un
 * comentario explícito reconociendo la duplicación intencional). Cero
 * cambio de comportamiento: cada servicio conserva su propia guarda de
 * `n < 2` y su propio criterio de redondeo (distintos entre sí antes de
 * esta extracción), solo se comparte el cálculo de la pendiente en sí.
 */
trait ComputesLinearSlope
{
    /**
     * Pendiente de una regresión lineal simple de $values frente a los
     * índices 0..n-1 (equiespaciados). Asume $values no vacío — cada
     * llamador aplica su propia guarda antes de invocar esto (ver
     * linearSlope() en cada servicio), igual que ya hacía el código
     * original.
     */
    private function computeRawLinearSlope(array $values): float
    {
        $n = count($values);
        $xs = range(0, $n - 1);
        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($values) / $n;

        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($xs as $i => $x) {
            $numerator += ($x - $meanX) * ($values[$i] - $meanY);
            $denominator += ($x - $meanX) ** 2;
        }

        return $denominator == 0.0 ? 0.0 : $numerator / $denominator;
    }
}
