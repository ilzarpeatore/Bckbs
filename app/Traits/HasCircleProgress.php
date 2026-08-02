<?php

namespace App\Traits;

trait HasCircleProgress
{
    public function getCircleProgress($current, $goal, $radius = 95)
    {
        $current = (float) $current;
        $goal    = (float) $goal;
        $radius  = (float) $radius;

        $circumference = 2 * pi() * $radius;

        if ($goal > 0) {
            $percentage = ($current / $goal) * 100;
            $percentage = max(0, min(100, $percentage));
        } else {
            $percentage = 0;
        }

        $offset = $circumference * (1 - $percentage / 100);

        return [
            'radius'        => $radius,
            'circumference' => $circumference,
            'percentage'    => round($percentage, 2),
            'offset'        => $offset,
        ];
    }
}
