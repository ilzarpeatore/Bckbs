<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BodyMetricType;
use App\Models\ClientBodyMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espejo cliente de API\Admin\ClientBodyMetricController — mismo modelo y
 * misma logica de agrupacion por tipo/fecha, pero siempre scoped al propio
 * usuario autenticado (auth('sanctum')->id()), nunca a un client_id
 * arbitrario. El coach sigue registrando medidas "oficiales" desde el panel
 * admin (source='coach'); esto es lo que el cliente ve/añade desde la app
 * (source='client').
 */
class BodyMetricController extends Controller
{
    // Catálogo de tipos disponibles para el cliente autenticado — global +
    // los que el coach haya creado a medida para este cliente concreto
    // (BodyMetricType::scope='client'). Usado por la app para poblar el
    // selector de "qué medida añadir" en vez de una lista fija en el
    // frontend, así un tipo nuevo creado desde el admin aparece sin publicar
    // una nueva versión de la app.
    public function types(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id();
        $types = BodyMetricType::visibleTo($userId)
            ->orderBy('order')
            ->orderBy('label')
            ->get(['value', 'label', 'unit']);

        return $this->sendResponse($types, 'Body metric types retrieved successfully');
    }

    public function index(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id();
        $query = ClientBodyMetric::where('client_id', $userId);

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

        return $this->sendResponse($metrics, 'Body metrics retrieved successfully');
    }

    public function chart(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id();
        $types = $request->input('types')
            ? explode(',', $request->input('types'))
            : BodyMetricType::visibleTo($userId)->pluck('value')->all();

        $days = (int) $request->input('days', 180);
        $fromDate = now()->subDays($days);

        $metrics = ClientBodyMetric::where('client_id', $userId)
            ->whereIn('metric_type', $types)
            ->where('recorded_at', '>=', $fromDate)
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('metric_type');

        $chartData = [];
        foreach ($metrics as $type => $records) {
            $chartData[$type] = [
                'unit' => $records->first()->unit,
                'data' => $records->map(fn ($r) => [
                    'id' => $r->id,
                    'value' => (float) $r->value,
                    'date' => $r->recorded_at->toDateString(),
                    'source' => $r->source,
                    'notes' => $r->notes,
                ])->values()->toArray(),
            ];
        }

        return $this->sendResponse($chartData, 'Chart data retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metric_type' => 'required|string|max:60',
            'value' => 'required|numeric',
            'unit' => 'nullable|string|max:50',
            'recorded_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $userId = auth('sanctum')->id();

        // Contra el catálogo dinámico (global + tipos custom del coach para
        // este cliente) en vez de una lista fija — así un tipo nuevo creado
        // desde el admin es utilizable de inmediato, sin publicar la app.
        if (!BodyMetricType::visibleTo($userId)->where('value', $validated['metric_type'])->exists()) {
            return response()->json(['message' => 'Tipo de métrica no válido'], 422);
        }

        $metric = ClientBodyMetric::create($validated + [
            'client_id' => $userId,
            'source' => 'client',
            'recorded_by_user_id' => $userId,
        ]);

        return $this->sendResponse($metric, 'Body metric logged successfully');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:client_body_metrics,id',
        ]);

        // Un cliente solo puede borrar sus propias medidas auto-reportadas,
        // nunca una medida oficial que el coach haya registrado desde el
        // admin (source='coach') — evita que se pierda el historial oficial
        // por error.
        $metric = ClientBodyMetric::where('id', $validated['id'])
            ->where('client_id', auth('sanctum')->id())
            ->where('source', 'client')
            ->firstOrFail();

        $metric->delete();

        return $this->sendResponse(null, 'Body metric deleted successfully');
    }
}
