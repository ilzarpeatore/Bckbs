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
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:equipment,slug,' . $id,
            'status' => 'sometimes|in:active,inactive',
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
