<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyMetricType;
use App\Models\ClientBodyMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientBodyMetricController extends Controller
{
    public function getList(Request $request): JsonResponse
    {
        $query = ClientBodyMetric::query();

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('metric_type')) {
            $query->where('metric_type', $request->metric_type);
        }
        if ($request->filled('from_date')) {
            $query->where('recorded_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('recorded_at', '<=', $request->to_date);
        }

        $perPage = min((int) $request->input('per_page', 50), 250);
        $metrics = $query->orderByDesc('recorded_at')->paginate($perPage);

        return $this->sendResponse($metrics, 'Client body metrics retrieved successfully');
    }

    public function getChartData(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
        ]);

        $clientId = $request->client_id;
        $types = $request->input('types')
            ? explode(',', $request->input('types'))
            : BodyMetricType::visibleTo($clientId)->pluck('value')->all();

        $days = (int) $request->input('days', 90);
        $fromDate = now()->subDays($days);

        $metrics = ClientBodyMetric::where('client_id', $clientId)
            ->whereIn('metric_type', $types)
            ->where('recorded_at', '>=', $fromDate)
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('metric_type');

        $chartData = [];
        foreach ($metrics as $type => $records) {
            $chartData[$type] = [
                'unit' => $records->first()->unit,
                'data' => $records->map(fn($r) => [
                    'value' => (float) $r->value,
                    'date' => $r->recorded_at->toDateString(),
                    'notes' => $r->notes,
                ])->values()->toArray(),
            ];
        }

        return $this->sendResponse($chartData, 'Chart data retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:users,id',
            'metric_type' => 'required|string|max:60',
            'value' => 'required|numeric',
            'unit' => 'nullable|string|max:50',
            'recorded_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if (!BodyMetricType::visibleTo($validated['client_id'])->where('value', $validated['metric_type'])->exists()) {
            return response()->json(['message' => 'Tipo de métrica no válido'], 422);
        }

        // Lo que registra el coach desde el panel admin siempre queda marcado
        // como 'coach' (distinto de lo que el propio cliente añade desde la
        // app, ver API\BodyMetricController) para que el historial distinga
        // medida oficial del entrenador vs auto-reportada.
        $metric = ClientBodyMetric::create($validated + [
            'source' => 'coach',
            'recorded_by_user_id' => auth('sanctum')->id(),
        ]);

        return $this->sendResponse($metric, 'Body metric logged successfully');
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:client_body_metrics,id',
            'metric_type' => 'sometimes|string|max:60',
            'value' => 'sometimes|numeric',
            'unit' => 'nullable|string|max:50',
            'recorded_at' => 'sometimes|date',
            'notes' => 'nullable|string',
        ]);

        $metric = ClientBodyMetric::findOrFail($validated['id']);

        if (isset($validated['metric_type']) && !BodyMetricType::visibleTo($metric->client_id)->where('value', $validated['metric_type'])->exists()) {
            return response()->json(['message' => 'Tipo de métrica no válido'], 422);
        }

        $metric->update(collect($validated)->except('id')->filter()->toArray());

        return $this->sendResponse($metric, 'Body metric updated successfully');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:client_body_metrics,id',
        ]);

        ClientBodyMetric::findOrFail($validated['id'])->delete();

        return $this->sendResponse(null, 'Body metric deleted successfully');
    }
}
