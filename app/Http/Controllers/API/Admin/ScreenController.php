<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Screen;
use Illuminate\Http\Request;

class ScreenController extends BaseController
{
    protected function getModelClass(): string
    {
        return Screen::class;
    }

    protected function getResourceClass(): string
    {
        return \App\Http\Resources\LanguageTableResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'screenId'   => 'required|string|max:255',
            'screenName' => 'required|string|max:255',
        ];
    }
}
