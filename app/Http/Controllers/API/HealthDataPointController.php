<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HealthDataPoint;
use Illuminate\Http\Request;

/**
 * Motor de Auto-Regulación de Carga — Fase 4 (readiness score, documento
 * §4.1). El cliente llama a este endpoint periódicamente (ej. al abrir
 * Home) con las lecturas nuevas leídas por helper/health.ts. Sin gate de
 * tier aquí — sincronizar datos crudos no cuesta nada y simplifica el
 * frontend; el gate se aplica en el job diario de cálculo
 * (ReadinessCalculationService), que es el punto de entrada real de la
 * feature (regla del plan: un solo gate por flujo, en el punto de entrada).
 */
class HealthDataPointController extends Controller
{
    /**
     * POST /api/health-data-points/sync
     *
     * Body: { readings: [{ source, metric_type, value, recorded_date }, ...] }
     * updateOrCreate por (client_id, source, metric_type, recorded_date) ->
     * idempotente si el dispositivo reenvía la lectura del mismo día
     * (ej. HealthKit corrige el valor de sueño más tarde).
     */
    public function sync(Request $request)
    {
        $request->validate([
            'readings'                    => 'required|array|min:1|max:200',
            'readings.*.source'           => 'required|in:apple_health,google_health,manual',
            'readings.*.metric_type'      => 'required|in:hrv,sleep_hours,resting_hr,steps',
            'readings.*.value'            => 'required|numeric|min:0',
            'readings.*.recorded_date'    => 'required|date|before_or_equal:today',
        ]);

        $clientId = auth('sanctum')->id();
        $now = now();
        $saved = [];

        foreach ($request->input('readings') as $reading) {
            $saved[] = HealthDataPoint::updateOrCreate(
                [
                    'client_id'     => $clientId,
                    'source'        => $reading['source'],
                    'metric_type'   => $reading['metric_type'],
                    'recorded_date' => $reading['recorded_date'],
                ],
                [
                    'value'     => $reading['value'],
                    'synced_at' => $now,
                ]
            );
        }

        return json_custom_response(['data' => ['synced' => count($saved)]]);
    }
}
