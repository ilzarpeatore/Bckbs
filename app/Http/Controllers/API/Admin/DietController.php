<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Diet;
use App\Http\Resources\DietResource;
use Illuminate\Http\Request;

class DietController extends BaseController
{
    protected function getModelClass(): string
    {
        return Diet::class;
    }

    protected function getResourceClass(): string
    {
        return DietResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'            => 'required|string|max:255',
            'slug'             => 'sometimes|string|max:255|unique:diets,slug,' . $id,
            'categorydiet_id'  => 'required|exists:category_diets,id',
            'calories'         => 'required|numeric',
            'carbs'            => 'required|numeric',
            'protein'          => 'required|numeric',
            'fat'              => 'required|numeric',
            'servings'         => 'required|integer',
            'total_time'       => 'nullable|string',
            'is_featured'      => 'sometimes|boolean',
            'is_premium'       => 'sometimes|boolean',
            'visibility'       => 'sometimes|in:all,premium',
            'ingredients'      => 'nullable|string',
            'description'      => 'nullable|string',
            'status'           => 'sometimes|in:active,inactive',
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
