<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\WorkoutType;
use App\Http\Resources\WorkoutTypeResource;
use Illuminate\Http\Request;

class WorkoutTypeController extends BaseController
{
    protected function getModelClass(): string
    {
        return WorkoutType::class;
    }

    protected function getResourceClass(): string
    {
        return WorkoutTypeResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:workout_types,slug,' . $id,
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
