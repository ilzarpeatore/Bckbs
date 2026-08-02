<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Tags;
use App\Http\Resources\TagsResource;
use Illuminate\Http\Request;

class TagsController extends BaseController
{
    protected function getModelClass(): string
    {
        return Tags::class;
    }

    protected function getResourceClass(): string
    {
        return TagsResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:tags,slug,' . $id,
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
