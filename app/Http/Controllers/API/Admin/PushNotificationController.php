<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\PushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PushNotificationController extends BaseController
{
    protected function getModelClass(): string
    {
        return PushNotification::class;
    }

    protected function getResourceClass(): string
    {
        return \App\Http\Resources\PushNotificationResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'   => 'required|string|max:255',
            'message' => 'required|string',
        ];
    }
}
