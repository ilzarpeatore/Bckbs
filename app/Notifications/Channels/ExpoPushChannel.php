<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Canal de notificacion para Expo Push Notifications (decision 2026-09-11,
 * ver auditoria del panel React de esta sesion: OneSignal nunca se termino
 * de integrar -- credenciales vacias en .env y el cliente nunca incluyo su
 * SDK. Expo Push reutiliza expo-notifications, ya instalado en el cliente,
 * sin SDK nativo adicional).
 *
 * Contrato real de la API de Expo (https://exp.host/--/api/v2/push/send):
 * request es SIEMPRE un array de mensajes (incluso para uno solo), response
 * es {"data": [{"status": "ok"|"error", ...}]} en el mismo orden. Un token
 * con formato invalido o de un dispositivo desinstalado NO produce un error
 * HTTP -- viene como status:"error" dentro de data[], por eso se loguea en
 * vez de lanzar excepcion (igual de "silencioso" que decidiamos evitar, pero
 * aqui SI queda rastro en los logs, a diferencia del bug real de OneSignal).
 */
class ExpoPushChannel
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function send($notifiable, Notification $notification)
    {
        $token = $notifiable->expo_push_token;

        if (!$token || !method_exists($notification, 'toExpoPush')) {
            return;
        }

        $message = $notification->toExpoPush($notifiable);

        if (!$message) {
            return;
        }

        $response = Http::acceptJson()
            ->asJson()
            ->post(self::ENDPOINT, [$message]);

        $result = $response->json('data.0');

        if (!$response->successful() || ($result['status'] ?? null) !== 'ok') {
            Log::warning('ExpoPushChannel: push no entregado', [
                'user_id'  => $notifiable->id ?? null,
                'token'    => $token,
                'http_ok'  => $response->successful(),
                'response' => $result,
            ]);
        }
    }
}
