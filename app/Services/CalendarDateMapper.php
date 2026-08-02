<?php

namespace App\Services;

use Carbon\Carbon;

class CalendarDateMapper
{
    /**
     * Dado un `start_date` (el lunes de la Semana 1, o cualquier fecha —
     * se ajusta al lunes de esa semana automáticamente) y un
     * (week_number, day_of_week), calcula la fecha real del calendario.
     *
     * day_of_week: 1=Lunes ... 7=Domingo (mismo criterio que ya usa
     * program_day_assignments).
     */
    public function toRealDate(Carbon $start_date, int $week_number, int $day_of_week): Carbon
    {
        $monday_of_week_1 = $start_date->copy()->startOfWeek(Carbon::MONDAY);

        return $monday_of_week_1
            ->addWeeks($week_number - 1)
            ->addDays($day_of_week - 1);
    }

    /**
     * La operación inversa: dada una fecha real y el start_date de
     * referencia, calcula a qué (week_number, day_of_week) corresponde.
     * Se usa cuando el coach asigna un entrenamiento haciendo clic
     * directamente sobre un día del calendario mensual real.
     */
    public function toWeekAndDay(Carbon $start_date, Carbon $target_date): array
    {
        $monday_of_week_1 = $start_date->copy()->startOfWeek(Carbon::MONDAY);
        $target_monday    = $target_date->copy()->startOfWeek(Carbon::MONDAY);

        $week_number = $monday_of_week_1->diffInWeeks($target_monday) + 1;
        $day_of_week = $target_date->dayOfWeekIso; // Carbon: 1=Lunes ... 7=Domingo, coincide con nuestro criterio

        return ['week_number' => (int) $week_number, 'day_of_week' => (int) $day_of_week];
    }

    /**
     * Todas las fechas reales de un mes concreto (para pintar la
     * cuadrícula del calendario, incluyendo los días de los meses
     * anterior/siguiente que "asoman" para completar semanas visuales).
     */
    public function getMonthGridDates(int $year, int $month): array
    {
        $first_of_month = Carbon::create($year, $month, 1);
        $grid_start = $first_of_month->copy()->startOfWeek(Carbon::MONDAY);
        $last_of_month = $first_of_month->copy()->endOfMonth();
        $grid_end = $last_of_month->copy()->endOfWeek(Carbon::SUNDAY);

        $dates = [];
        $cursor = $grid_start->copy();
        while ($cursor->lte($grid_end)) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        return $dates;
    }
}
