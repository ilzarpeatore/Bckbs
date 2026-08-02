<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\LanguageList;
use Illuminate\Http\Request;

class LanguageController extends BaseController
{
    protected function getModelClass(): string
    {
        return LanguageList::class;
    }

    protected function getResourceClass(): string
    {
        return \App\Http\Resources\LanguageTableResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'language_id'    => 'required|string|max:10',
            'language_name'  => 'required|string|max:255',
            'language_code'  => 'required|string|max:10',
            'country_code'   => 'required|string|max:10',
            'language_flag'  => 'nullable|string|max:10',
            'is_rtl'         => 'sometimes|boolean',
            'status'         => 'sometimes|in:active,inactive',
            'is_default'     => 'sometimes|boolean',
        ];
    }
}
