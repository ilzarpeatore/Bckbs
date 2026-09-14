<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Equipment;
use App\Http\Resources\EquipmentResource;
use Illuminate\Http\Request;

class EquipmentController extends BaseController
{
    protected function getModelClass(): string
    {
        return Equipment::class;
    }

    protected function getResourceClass(): string
    {
        return EquipmentResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'     => 'required|string|max:255',
            'slug'      => 'sometimes|string|max:255|unique:equipment,slug,' . $id,
            'status'    => 'sometimes|in:active,inactive',
            // Motor de Auto-Regulación de Carga (esta sesión): sin
            // clasificar, un equipo nuevo cae al RoundingMode genérico de
            // la regla en vez del redondeo por tipo de equipo real.
            'load_type' => 'nullable|in:plate,dumbbell,fixed',
        ];
    }

    protected function afterSave($item, Request $request): void
    {
        if ($request->hasFile('image')) {
            $item->clearMediaCollection('image');
            $item->addMediaFromRequest('image')->toMediaCollection('image');
        }
    }
}
