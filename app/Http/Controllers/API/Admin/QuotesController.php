<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Quotes;
use App\Http\Resources\QuotesResource;
use Illuminate\Http\Request;

class QuotesController extends BaseController
{
    protected function getModelClass(): string
    {
        return Quotes::class;
    }

    protected function getResourceClass(): string
    {
        return QuotesResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'   => 'required|string|max:255',
            'slug'    => 'sometimes|string|max:255|unique:quotes,slug,' . $id,
            'message' => 'required|string',
            'date'    => 'nullable|date',
        ];
    }
}
