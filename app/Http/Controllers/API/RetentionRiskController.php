<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CoachScoreWeightConfig;
use App\Models\RetentionRiskScore;
use App\Models\User;
use App\Services\RetentionRiskCalculationService;
use Illuminate\Http\Request;

/**
 * Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md
 * §9). Self-service (coach_id = auth()->id(), sin prefijo /admin) + espejo
 * /admin (AÑADIDO, no pedido por el documento original) -- petición
 * explícita del usuario tras ver la feature: quiere configurar pesos/
 * mensajes y ver el score desde el panel Vite/React, que se autentica vía
 * `admin.api` (staff, no la sesión del coach) -- mismo motivo ya
 * documentado en CoachExceptionItemController para su espejo /admin: es la
 * única superficie real donde hoy se opera sobre datos de un coach. Toda la
 * lógica vive en los métodos `do*()` privados; los públicos solo resuelven
 * autorización/parámetros y delegan.
 */
class RetentionRiskController extends Controller
{
    private const HISTORY_DAYS = 90;

    // ═══ Self-service (coach_id = auth()->id()) ═══════════════════════════

    /** GET /api/coaches/{id}/retention-risk-summary */
    public function summary(Request $request, $coachId)
    {
        if (!$this->authorizeCoach($request, $coachId)) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        return json_custom_response(['data' => $this->doSummary((int) $coachId)]);
    }

    /** GET /api/coaches/{id}/clients/{clientId}/retention-risk */
    public function detail(Request $request, $coachId, $clientId)
    {
        if (!$this->authorizeCoach($request, $coachId)) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        $client = User::where('id', $clientId)->where('coach_id', (int) $coachId)->first();
        if (!$client) {
            return json_custom_response(['message' => 'Cliente no encontrado para este coach.'], 404);
        }

        return json_custom_response(['data' => $this->doHistory($client)]);
    }

    /** GET /api/coaches/{id}/retention-risk-config */
    public function getConfig(Request $request, $coachId)
    {
        if (!$this->authorizeCoach($request, $coachId)) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        return json_custom_response(['data' => $this->doGetConfig((int) $coachId)]);
    }

    /** PUT /api/coaches/{id}/retention-risk-weights */
    public function updateWeights(Request $request, $coachId)
    {
        if (!$this->authorizeCoach($request, $coachId)) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'w1' => 'required|numeric|min:0|max:1',
            'w2' => 'required|numeric|min:0|max:1',
            'w3' => 'required|numeric|min:0|max:1',
            'w4' => 'required|numeric|min:0|max:1',
        ]);

        $result = $this->doUpdateWeights((int) $coachId, $data);
        if ($result === null) {
            return json_custom_response(['message' => 'Los pesos no pueden sumar 0.'], 422);
        }

        return json_custom_response(['data' => $result]);
    }

    /** PUT /api/coaches/{id}/retention-settings */
    public function updateSettings(Request $request, $coachId)
    {
        if (!$this->authorizeCoach($request, $coachId)) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        $data = $this->validateSettings($request);

        return json_custom_response(['data' => $this->doUpdateSettings((int) $coachId, $data)]);
    }

    // ═══ Espejo /admin (staff, sin restricción de auth()->id()) ═══════════

    /** GET /admin/retention-risk-summary?coach_id= (opcional -- sin él, todos los coaches) */
    public function adminSummary(Request $request)
    {
        $request->validate(['coach_id' => 'nullable|exists:users,id']);
        $coachId = $request->filled('coach_id') ? (int) $request->coach_id : null;

        return json_custom_response(['data' => $this->doSummary($coachId)]);
    }

    /** GET /admin/retention-risk-history?client_id= */
    public function adminHistory(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);
        $client = User::find($request->client_id);

        return json_custom_response(['data' => $this->doHistory($client)]);
    }

    /** GET /admin/retention-risk-config?coach_id= */
    public function adminGetConfig(Request $request)
    {
        $request->validate(['coach_id' => 'required|exists:users,id']);

        return json_custom_response(['data' => $this->doGetConfig((int) $request->coach_id)]);
    }

    /** PUT /admin/retention-risk-weights -- body: coach_id, w1..w4 */
    public function adminUpdateWeights(Request $request)
    {
        $data = $request->validate([
            'coach_id' => 'required|exists:users,id',
            'w1' => 'required|numeric|min:0|max:1',
            'w2' => 'required|numeric|min:0|max:1',
            'w3' => 'required|numeric|min:0|max:1',
            'w4' => 'required|numeric|min:0|max:1',
        ]);

        $coachId = (int) $data['coach_id'];
        unset($data['coach_id']);

        $result = $this->doUpdateWeights($coachId, $data);
        if ($result === null) {
            return json_custom_response(['message' => 'Los pesos no pueden sumar 0.'], 422);
        }

        return json_custom_response(['data' => $result]);
    }

    /** PUT /admin/retention-risk-settings -- body: coach_id, auto_reengagement_enabled, msg_dia_7?, msg_dia_14?, msg_dia_20? */
    public function adminUpdateSettings(Request $request)
    {
        $request->validate(['coach_id' => 'required|exists:users,id']);
        $data = $this->validateSettings($request);

        return json_custom_response(['data' => $this->doUpdateSettings((int) $request->coach_id, $data)]);
    }

    // ═══ Lógica compartida ══════════════════════════════════════════════

    private function validateSettings(Request $request): array
    {
        $data = $request->validate([
            'auto_reengagement_enabled' => 'required|boolean',
            'msg_dia_7'  => 'nullable|string|max:500',
            'msg_dia_14' => 'nullable|string|max:500',
            'msg_dia_20' => 'nullable|string|max:500',
        ]);

        // Cadena vacía = "quitar el override y volver al texto por defecto",
        // no un mensaje real de 0 caracteres -- se guarda como null.
        foreach (['msg_dia_7', 'msg_dia_14', 'msg_dia_20'] as $key) {
            if (array_key_exists($key, $data) && trim((string) $data[$key]) === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    private function doSummary(?int $coachId): array
    {
        $service = new RetentionRiskCalculationService();

        $clientIdsQuery = User::whereNotNull('coach_id');
        if ($coachId !== null) {
            $clientIdsQuery->where('coach_id', $coachId);
        }
        $clientIds = $clientIdsQuery->pluck('id');

        return RetentionRiskScore::whereIn('client_id', $clientIds)
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')->from('retention_risk_scores')->groupBy('client_id');
            })
            ->with('client:id,first_name,last_name,display_name,email,coach_id')
            ->get()
            ->map(fn (RetentionRiskScore $s) => $this->summaryRow($s, $service))
            ->sortByDesc(fn ($row) => $row['combined_score'] ?? -1)
            ->values()
            ->all();
    }

    private function doHistory(User $client): array
    {
        $service = new RetentionRiskCalculationService();

        $history = RetentionRiskScore::where('client_id', $client->id)
            ->where('date', '>=', now()->subDays(self::HISTORY_DAYS)->toDateString())
            ->orderBy('date')
            ->get();

        $current = $history->last();
        $current?->setRelation('client', $client);

        return [
            'current' => $current ? $this->summaryRow($current, $service) : null,
            'history' => $history->map(fn (RetentionRiskScore $s) => [
                'date'             => $s->date,
                'band'             => $s->band,
                'combined_score'   => $s->combined_score,
                'dias_inactividad' => $s->dias_inactividad,
            ])->values(),
        ];
    }

    private function doGetConfig(int $coachId): array
    {
        $config = CoachScoreWeightConfig::where('coach_id', $coachId)->where('score_type', 'retention_risk')->first();
        $defaultWeights = RetentionRiskCalculationService::defaultWeights();
        $defaultMessages = RetentionRiskCalculationService::defaultNudgeMessages();

        return [
            'w1' => $config?->w1,
            'w2' => $config?->w2,
            'w3' => $config?->w3,
            'w4' => $config?->w4,
            'auto_reengagement_enabled' => $config?->auto_reengagement_enabled ?? true,
            'msg_dia_7'  => $config?->msg_dia_7,
            'msg_dia_14' => $config?->msg_dia_14,
            'msg_dia_20' => $config?->msg_dia_20,
            'defaults' => [
                'w1' => $defaultWeights['w1'], 'w2' => $defaultWeights['w2'],
                'w3' => $defaultWeights['w3'], 'w4' => $defaultWeights['w4'],
                'msg_dia_7'  => $defaultMessages['dia_7'],
                'msg_dia_14' => $defaultMessages['dia_14'],
                'msg_dia_20' => $defaultMessages['dia_20'],
            ],
        ];
    }

    /** @return CoachScoreWeightConfig|null null = pesos sumaban 0 (error de validación en el llamador). */
    private function doUpdateWeights(int $coachId, array $data): ?CoachScoreWeightConfig
    {
        // §9: "validar que sumen 1.0 (o normalizar automáticamente si no
        // suman exactamente 1.0)" -- se elige normalizar, es más tolerante
        // con la UI del coach que rechazar la petición por un redondeo.
        $sum = $data['w1'] + $data['w2'] + $data['w3'] + $data['w4'];
        if ($sum <= 0) {
            return null;
        }
        foreach (['w1', 'w2', 'w3', 'w4'] as $key) {
            $data[$key] = round($data[$key] / $sum, 4);
        }

        return CoachScoreWeightConfig::updateOrCreate(
            ['coach_id' => $coachId, 'score_type' => 'retention_risk'],
            $data
        );
    }

    private function doUpdateSettings(int $coachId, array $data): CoachScoreWeightConfig
    {
        return CoachScoreWeightConfig::updateOrCreate(
            ['coach_id' => $coachId, 'score_type' => 'retention_risk'],
            $data
        );
    }

    private function authorizeCoach(Request $request, $coachId): bool
    {
        $authUser = $request->user();

        return $authUser && (int) $authUser->id === (int) $coachId;
    }

    private function summaryRow(RetentionRiskScore $s, RetentionRiskCalculationService $service): array
    {
        return [
            'client_id'          => $s->client_id,
            'client'             => $s->relationLoaded('client') && $s->client ? [
                'id'   => $s->client->id,
                'name' => $s->client->display_name ?: trim("{$s->client->first_name} {$s->client->last_name}") ?: $s->client->email,
            ] : null,
            'date'               => $s->date,
            'band'               => $s->band,
            'combined_score'     => $s->combined_score,
            'dias_inactividad'   => $s->dias_inactividad,
            'compliance_actual'  => $s->compliance_actual,
            'compliance_anterior' => $s->compliance_anterior,
            'dias_desde_ultimo_logro' => $s->dias_desde_ultimo_logro,
            'dolor_score'        => $s->dolor_score,
            'dominant_component' => $service->dominantComponent($s),
        ];
    }
}
