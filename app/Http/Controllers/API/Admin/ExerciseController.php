<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Exercise;
use App\Http\Resources\ExerciseResource;
use Illuminate\Http\Request;

class ExerciseController extends BaseController
{
    protected function getModelClass(): string
    {
        return Exercise::class;
    }

    protected function getResourceClass(): string
    {
        return ExerciseResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'           => 'required|string|max:255',
            'slug'            => 'sometimes|string|max:255|unique:exercises,slug,' . $id,
            'instruction'     => 'nullable|string',
            'tips'            => 'nullable|string',
            'video_type'      => 'sometimes|in:youtube,vimeo',
            'video_url'       => 'nullable|string',
            'bodypart_ids'    => 'nullable|string',
            'duration'        => 'nullable|string',
            'sets'            => 'nullable|string',
            'equipment_id'    => 'nullable|exists:equipment,id',
            'level_id'        => 'nullable|exists:levels,id',
            'is_premium'      => 'sometimes|boolean',
            'seconds_per_rep' => 'nullable|numeric',
            'status'          => 'sometimes|in:active,inactive',
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
