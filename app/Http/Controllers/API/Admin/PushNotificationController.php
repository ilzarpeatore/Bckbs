<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\PushNotification;
use App\Models\User;
use App\Notifications\CommonNotification;
use App\Notifications\DatabaseNotification;
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

    // AÑADIDO 2026-09-11, resuelto 2026-09-18: guardar una plantilla aquí ya
    // NO manda nada por sí solo -- store()/update() solo guardan en BD (el
    // "borrador" reutilizable). Enviarlo de verdad es una acción aparte, ver
    // send() más abajo, que sí acepta un selector de destinatario real
    // (todos / una selección concreta de clientes).
    protected function afterSave(Model $item, Request $request): void
    {
        //
    }

    /**
     * Envía de verdad un push -- a TODOS los usuarios o a una selección
     * concreta (multi-select, incluido el caso "un solo cliente" que usa el
     * botón "Enviar push" del perfil de cada cliente). `push_notification_id`
     * es opcional -- si se manda, vincula el envío a una plantilla ya
     * guardada (para trazabilidad); si no, es un envío puntual con
     * title/message directos, sin guardar plantilla.
     *
     * Se resuelve el recipient_count ANTES de responder (para que el coach
     * vea cuántos van a recibirlo), pero el envío en sí ocurre EN SEGUNDO
     * PLANO (dispatch()->afterResponse()) -- CommonNotification no implementa
     * ShouldQueue (solo usa el trait Queueable, que por sí solo no encola
     * nada) y este proyecto corre con QUEUE_CONNECTION=sync sin worker, así
     * que mandarlo a cientos/miles de usuarios de forma síncrona dentro de
     * la misma petición HTTP (una llamada de red a Expo/OneSignal por
     * usuario) arriesgaba timeout. afterResponse() no necesita un worker de
     * colas: PHP-FPM sigue ejecutando tras devolver la respuesta al cliente.
     */
    public function send(Request $request)
    {
        $request->validate([
            'push_notification_id' => 'nullable|exists:push_notifications,id',
            'title'                 => 'required_without:push_notification_id|nullable|string|max:255',
            'message'               => 'required_without:push_notification_id|nullable|string',
            'audience'              => 'required|in:all,selected',
            'user_ids'              => 'required_if:audience,selected|array|min:1',
            'user_ids.*'            => 'integer|exists:users,id',
        ]);

        $template = $request->push_notification_id ? PushNotification::find($request->push_notification_id) : null;
        $title = $request->title ?: optional($template)->title;
        $message = $request->message ?: optional($template)->message;

        if (!$title || !$message) {
            return json_message_response('Falta título o mensaje.', 422);
        }

        // FIX (reportado 2026-09-19: "no llega nada, ni push ni campana"):
        // $audience/$userIds se extraen a variables planas ANTES del
        // dispatch() -- el closure de abajo NO puede capturar $request
        // (`use ($request, ...)`, como estaba antes) porque Laravel serializa
        // el closure para construir el payload del job incluso en cola
        // `sync` (SyncQueue::push() -> Queue::createObjectPayload() ->
        // serialize()), y un objeto Request de Symfony reciente lleva un
        // WeakMap interno que no se puede serializar ("Serialization of
        // 'WeakMap' is not allowed", ver laravel.log 2026-09-19 13:29:19).
        // Como el fallo ocurre dentro de Kernel::terminate() -- después de
        // que afterResponse() ya envió la respuesta 200 al admin -- el panel
        // mostraba "Envío en curso" con éxito mientras el envío real moría
        // en silencio sin loguear nada más que esa excepción aislada.
        $audience = $request->audience;
        $userIds = $request->audience === 'selected' ? $request->user_ids : [];

        $recipientQuery = $audience === 'all'
            ? User::query()
            : User::whereIn('id', $userIds);

        $recipientCount = $recipientQuery->count();

        $notificationData = [
            'id'                    => optional($template)->id,
            'push_notification_id'  => optional($template)->id,
            'type'                  => 'push_notification',
            'subject'               => $title,
            'message'               => $message,
            'image'                 => null,
        ];

        dispatch(function () use ($audience, $userIds, $notificationData) {
            $query = $audience === 'all'
                ? User::query()
                : User::whereIn('id', $userIds);

            $query->chunkById(200, function ($users) use ($notificationData) {
                foreach ($users as $user) {
                    try {
                        // FIX (mismo reporte): antes solo se llamaba a
                        // CommonNotification (push OneSignal/Expo) -- a
                        // diferencia del panel Blade legacy
                        // (App\Http\Controllers\PushNotificationController::store()),
                        // nunca se guardaba un DatabaseNotification, así que
                        // la campana de notificaciones de la app (que lee
                        // $user->notifications(), tabla `notifications`, ver
                        // API\NotificationController::getList()) se quedaba
                        // vacía aunque el push sí hubiera llegado.
                        $user->notify(new DatabaseNotification($notificationData));
                        $user->notify(new CommonNotification('push_notification', $notificationData));
                    } catch (\Throwable $e) {
                        Log::warning("PushNotificationController@send: fallo al notificar al usuario {$user->id}: {$e->getMessage()}");
                    }
                }
            });
        })->afterResponse();

        return json_custom_response(['message' => 'Envío en curso.', 'data' => ['recipient_count' => $recipientCount]]);
    }
}
