<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DailyReadinessCheck;
use App\Models\ClientFeatureSetting;
use App\Models\ReadinessScore;

/**
 * Readiness diario obligatorio antes de Workout Preview (sueño, agujetas,
 * energía, estrés) - obligatorio por defecto, salvo que el admin lo
 * desactive para un cliente concreto (ClientFeatureSetting 'readiness_check',
 * opt-out por diseño, ver ClientFeatureSetting::isEnabledFor()).
 */
class ReadinessController extends Controller
{
    public function today(Request $request)
    {
        $user = auth('sanctum')->user();
        $today = now()->toDateString();

        $required = ClientFeatureSetting::isEnabledFor($user->id, 'readiness_check');

        $check = DailyReadinessCheck::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        return json_custom_response([
            'data' => [
                'required'        => $required,
                'submitted_today' => $check !== null,
                'today'           => $check ? [
                    'sleep_quality'  => $check->sleep_quality,
                    'soreness_level' => $check->soreness_level,
                    'energy_level'   => $check->energy_level,
                    'stress_level'   => $check->stress_level,
                ] : null,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sleep_quality'  => 'required|integer|min:1|max:5',
            'soreness_level' => 'required|integer|min:1|max:10',
            'energy_level'   => 'required|integer|min:1|max:5',
            'stress_level'   => 'required|integer|min:1|max:5',
        ]);

        $user = auth('sanctum')->user();

        $check = DailyReadinessCheck::updateOrCreate(
            ['user_id' => $user->id, 'date' => now()->toDateString()],
            [
                'sleep_quality'  => $request->sleep_quality,
                'soreness_level' => $request->soreness_level,
                'energy_level'   => $request->energy_level,
                'stress_level'   => $request->stress_level,
            ]
        );

        return json_custom_response(['data' => $check]);
    }

    /**
     * NUEVO — stopgap de readiness (item 10 del backlog, alcance
     * confirmado con producto): NO es el motor completo de "Fase 4"
     * (ReadinessCalculationService, readiness_scores con combined_score/
     * band/acwr/hrv_z_score, ingesta de wearable HRV/sueño) — esa pieza
     * no existe todavía en este repo y queda explícitamente FUERA de
     * alcance de este cambio, es una iniciativa aparte más grande. Esto
     * es solo un envoltorio ligero sobre el cuestionario subjetivo que
     * YA existe (DailyReadinessCheck), para que la app tenga algo que
     * mostrar mientras tanto.
     */
    public function summary(Request $request)
    {
        $user = auth('sanctum')->user();
        $today = now()->toDateString();

        $check = DailyReadinessCheck::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (!$check) {
            return json_custom_response([
                'data' => [
                    'has_data'       => false,
                    'combined_score' => null,
                    'band'           => null,
                    'calculated_at'  => null,
                ],
            ]);
        }

        // Formula del stopgap (SOLO subjetivo, no HRV/ACWR real): cada
        // campo del cuestionario (rangos según DailyReadinessCheck/su
        // migración: sleep_quality y energy_level 1-5, soreness_level
        // 1-10, stress_level 1-5) se normaliza a una escala 0-100
        // comparable, invirtiendo los que son "cuanto más alto, peor"
        // (soreness_level, stress_level) para que en las 4 escalas
        // normalizadas 100 sea siempre "mejor". combined_score = media
        // simple de las 4.
        $sleepScore    = self::normalize((float) $check->sleep_quality, 1, 5, false);
        $energyScore   = self::normalize((float) $check->energy_level, 1, 5, false);
        $sorenessScore = self::normalize((float) $check->soreness_level, 1, 10, true);
        $stressScore   = self::normalize((float) $check->stress_level, 1, 5, true);

        $combined = round(($sleepScore + $energyScore + $sorenessScore + $stressScore) / 4);

        if ($combined >= 75) {
            $band = 'good';
        } elseif ($combined >= 50) {
            $band = 'ok';
        } else {
            $band = 'poor';
        }

        return json_custom_response([
            'data' => [
                'has_data'       => true,
                'combined_score' => $combined,
                'band'           => $band,
                'calculated_at'  => now()->toIso8601String(),
                'raw' => [
                    'sleep_quality'  => $check->sleep_quality,
                    'soreness_level' => $check->soreness_level,
                    'energy_level'   => $check->energy_level,
                    'stress_level'   => $check->stress_level,
                ],
            ],
        ]);
    }

    /**
     * NUEVO (item 1 del roadmap, 2026-09-16) — expone al propio cliente el
     * `combined_score`/`band`/`acwr` REALES de `readiness_scores` (Motor de
     * Auto-Regulación, Fase 4 — `ReadinessCalculationService`, job diario
     * `readiness:calculate`, solo para clientes paid-tier), en vez de la
     * aproximación 100% subjetiva de `summary()` (arriba) — esa se queda
     * como fallback en el cliente para cuando esto no tenga datos todavía
     * (usuario free-tier, o el job diario aún no ha corrido para hoy).
     * Distinto de `Admin\ReportController::clientReadiness()` (mismo dato,
     * pero para que el coach consulte a un cliente concreto desde el panel).
     *
     * Nota real: `hrv_z_score`/`sueno_z_score` salen siempre `null` en la
     * práctica — la app (`bsa`) ya no sincroniza datos de wearable
     * (`helper/health.ts` se eliminó del repo, sin integración de
     * HealthKit/Health Connect), así que hoy `combined_score` se compone
     * solo de `subjetivo_score` + `acwr` (ver
     * `ReadinessCalculationService::combine()`). Documentado aquí para que
     * no sorprenda si se retoma la integración de salud más adelante.
     */
    public function latest(Request $request)
    {
        $user = auth('sanctum')->user();

        $score = ReadinessScore::where('client_id', $user->id)
            ->orderBy('date', 'desc')
            ->first();

        if (!$score) {
            return json_custom_response([
                'data' => [
                    'has_data'        => false,
                    'date'            => null,
                    'combined_score'  => null,
                    'band'            => null,
                    'acwr'            => null,
                    'hrv_z_score'     => null,
                    'sueno_z_score'   => null,
                    'subjetivo_score' => null,
                    'calculated_at'   => null,
                ],
            ]);
        }

        return json_custom_response([
            'data' => [
                'has_data'        => true,
                'date'            => $score->date->toDateString(),
                'combined_score'  => $score->combined_score,
                'band'            => $score->band,
                'acwr'            => $score->acwr,
                'hrv_z_score'     => $score->hrv_z_score,
                'sueno_z_score'   => $score->sueno_z_score,
                'subjetivo_score' => $score->subjetivo_score,
                'calculated_at'   => $score->calculated_at?->toIso8601String(),
            ],
        ]);
    }

    /** Escala $value (rango [$min,$max]) a 0-100; $invert=true cuando un valor más alto es peor. */
    private static function normalize(float $value, float $min, float $max, bool $invert): float
    {
        $value = max($min, min($max, $value));
        $ratio = ($value - $min) / ($max - $min);

        return ($invert ? (1 - $ratio) : $ratio) * 100;
    }

    /**
     * Historial propio de readiness diario (pantalla Check-ins > Historial de la app).
     * Mismo shape que ClientProfileCalendarController::getReadinessChecks (admin), pero
     * siempre del usuario autenticado: nunca acepta un client_id.
     *
     * GET v1/readiness-history?limit=60  (limit 1..120, por defecto 60)
     */
    public function history(Request $request)
    {
        $request->validate(['limit' => 'nullable|integer|min:1|max:120']);

        $checks = DailyReadinessCheck::where('user_id', auth('sanctum')->id())
            ->orderByDesc('date')
            ->limit((int) ($request->limit ?? 60))
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'date'           => optional($c->date)->toDateString(),
                'sleep_quality'  => $c->sleep_quality,
                'soreness_level' => $c->soreness_level,
                'energy_level'   => $c->energy_level,
                'stress_level'   => $c->stress_level,
            ]);

        return json_custom_response(['data' => $checks]);
    }
}
