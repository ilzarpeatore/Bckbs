<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\BlogCategory;
use App\Http\Resources\BlogCategoryResource;
use Illuminate\Http\Request;

class BlogCategoryController extends BaseController
{
    protected function getModelClass(): string
    {
        return BlogCategory::class;
    }

    protected function getResourceClass(): string
    {
        return BlogCategoryResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:blog_categories,slug,' . $id,
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
