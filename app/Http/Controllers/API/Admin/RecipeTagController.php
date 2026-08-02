<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\RecipeTag;
use App\Http\Resources\RecipeTagResource;
use Illuminate\Http\Request;

class RecipeTagController extends BaseController
{
    protected function getModelClass(): string
    {
        return RecipeTag::class;
    }

    protected function getResourceClass(): string
    {
        return RecipeTagResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:recipe_tags,slug,' . $id,
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
