<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\DefaultKeyword;
use Illuminate\Http\Request;

class DefaultKeywordController extends BaseController
{
    protected function getModelClass(): string
    {
        return DefaultKeyword::class;
    }

    protected function getResourceClass(): string
    {
        return \App\Http\Resources\LanguageTableResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'screen_id'     => 'required|exists:screens,id',
            'keyword_id'    => 'required|string|max:255',
            'keyword_name'  => 'required|string|max:255',
            'keyword_value' => 'required|string',
        ];
    }
}
