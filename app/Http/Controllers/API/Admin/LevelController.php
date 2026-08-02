<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Level;
use App\Http\Resources\LevelResource;
use Illuminate\Http\Request;

class LevelController extends BaseController
{
    protected function getModelClass(): string
    {
        return Level::class;
    }

    protected function getResourceClass(): string
    {
        return LevelResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:levels,slug,' . $id,
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
