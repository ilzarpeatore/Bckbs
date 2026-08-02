<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\BodyPart;
use App\Http\Resources\BodyPartResource;
use Illuminate\Http\Request;

class BodyPartController extends BaseController
{
    protected function getModelClass(): string
    {
        return BodyPart::class;
    }

    protected function getResourceClass(): string
    {
        return BodyPartResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:body_parts,slug,' . $id,
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
