<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\BodyMetricType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo dinámico de tipos de medida corporal — contrato ya escrito y
 * consumido por UserDetailView.tsx (panel admin, sección Métricas del
 * perfil de cliente) contra endpoints que hasta ahora solo existían como
 * mock MSW (nunca tuvieron backend real). Este controller cierra ese hueco
 * sin tocar el frontend admin (mismas rutas/payloads que ya esperaba).
 */
class BodyMetricTypeController extends Controller
{
    public function getList(Request $request): JsonResponse
    {
        $clientId = $request->filled('client_id') ? (int) $request->client_id : null;

        $types = BodyMetricType::visibleTo($clientId)
            ->orderBy('order')
            ->orderBy('label')
            ->get(['value', 'label', 'unit', 'scope', 'client_id']);

        return $this->sendResponse($types, 'Body metric types retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required|string|max:60',
            'label' => 'required|string|max:100',
            'unit' => 'nullable|string|max:20',
            'scope' => 'nullable|in:global,client',
            'client_id' => 'nullable|exists:users,id|required_if:scope,client',
        ]);

        $scope = $validated['scope'] ?? 'global';
        $clientId = $scope === 'client' ? $validated['client_id'] : null;

        $exists = BodyMetricType::where('value', $validated['value'])
            ->where('scope', $scope)
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId), fn ($q) => $q->whereNull('client_id'))
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Ya existe un tipo con ese valor en este ámbito'], 400);
        }

        $type = BodyMetricType::create([
            'value' => $validated['value'],
            'label' => $validated['label'],
            'unit' => $validated['unit'] ?? null,
            'scope' => $scope,
            'client_id' => $clientId,
            'order' => (BodyMetricType::max('order') ?? 0) + 1,
        ]);

        return $this->sendResponse($type, 'Body metric type created successfully');
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required|string|max:60',
            'label' => 'required|string|max:100',
            'unit' => 'nullable|string|max:20',
            'client_id' => 'nullable|exists:users,id',
        ]);

        $query = BodyMetricType::where('value', $validated['value']);
        $query = $validated['client_id'] ?? null
            ? $query->where('scope', 'client')->where('client_id', $validated['client_id'])
            : $query->where('scope', 'global');

        $type = $query->firstOrFail();
        $type->update(['label' => $validated['label'], 'unit' => $validated['unit'] ?? null]);

        return $this->sendResponse($type, 'Body metric type updated successfully');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required|string',
            'client_id' => 'nullable|exists:users,id',
        ]);

        $query = BodyMetricType::where('value', $validated['value']);
        $query = $validated['client_id'] ?? null
            ? $query->where('scope', 'client')->where('client_id', $validated['client_id'])
            : $query->where('scope', 'global');

        $type = $query->firstOrFail();
        $type->delete();

        return $this->sendResponse(null, 'Body metric type deleted successfully');
    }
}
