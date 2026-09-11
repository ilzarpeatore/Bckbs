<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\PushNotification;
use App\Models\User;
use App\Notifications\CommonNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    // AÑADIDO 2026-09-11: antes, crear una fila aquí solo la guardaba en BD
    // -- BaseController::store()/update() nunca llaman a $user->notify(),
    // a diferencia del panel Blade viejo (App\Http\Controllers\
    // PushNotificationController::store()), que sí lo hacía por cada
    // usuario seleccionado. Este formulario (solo título+mensaje, sin
    // selector de destinatario) no tiene forma de elegir usuarios todavía,
    // así que -- pedido explícito -- cada push creado desde aquí se manda
    // solo a la cuenta demo, nunca a la base de usuarios real, hasta que
    // se añada un selector de destinatario real.
    protected function afterSave(Model $item, Request $request): void
    {
        if (!$item->wasRecentlyCreated) {
            return;
        }

        $user = User::where('email', 'demo@bestronger.app')->first();
        if (!$user) {
            Log::warning('PushNotificationController@afterSave: cuenta demo@bestronger.app no encontrada, no se envía el push de prueba.');
            return;
        }

        $user->notify(new CommonNotification('push_notification', [
            'id'                    => $item->id,
            'push_notification_id'  => $item->id,
            'type'                  => 'push_notification',
            'subject'               => $item->title,
            'message'               => $item->message,
            'image'                 => null,
        ]));
    }
}
