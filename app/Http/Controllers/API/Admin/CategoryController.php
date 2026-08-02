<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Category;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
    protected function getModelClass(): string
    {
        return Category::class;
    }

    protected function getResourceClass(): string
    {
        return CategoryResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:categories,slug,' . $id,
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
