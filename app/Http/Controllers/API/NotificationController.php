<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\Notification;
use App\Http\Controllers\Controller;

use App\Http\Resources\NotificationResource;

class NotificationController extends Controller
{
    public function getList(Request $request)
    {
        $user = auth()->user();

        $user->last_notification_seen = now();
        $user->save();

        $page = isset($request->page) ? $request->page : 1;
        $limit = isset($request->limit) ? $request->limit : config('constant.PER_PAGE_LIMIT');

        $all_unread_count = $user->unreadNotifications()->count();

        $type = isset($request->type) ? $request->type : null;
        if ($type == "markas_read" && $all_unread_count > 0) {
            $user->unreadNotifications()->markAsRead();
            $all_unread_count = 0;
        }

        $notifications = $user->notifications()
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $items = NotificationResource::collection($notifications);
        
        $response = [
            'notification_data' => $items,
            'all_unread_count' => $all_unread_count,
        ];

        return json_custom_response($response);
    }

    public function getNotificationDetail(Request $request)
    {
        $id = request('id');
        $user = auth()->user();

        // SEGURIDAD (barrido sistematico 2026-09-01, MEDIO): sin scope, cualquier
        // usuario autenticado podia leer el contenido de la notificacion de OTRO
        // usuario enumerando id. Se escopa igual que el resto del metodo (que ya
        // usa $user->notifications() para el update mas abajo).
        $notification = $user->notifications()->where('id', $id)->first();

        // Antes del fix de arriba esto nunca podia ser null en la practica (la
        // query no escopaba y encontraba cualquier notificacion real) -- ahora
        // que si escopa, un id ajeno o inexistente produce null, y
        // NotificationResource no tolera un modelo null (accede a ->data
        // directamente). Se corta aqui explicitamente en vez de dejar que
        // rompa con un 500.
        if ($notification === null) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.data')]), 400);
        }

        $notification_detail = new NotificationResource($notification);

        $all_unread_count = $user->unreadNotifications()->count();

        $user->notifications()->where('id', $id)->update(['read_at' => now()]);

        if ($all_unread_count > 0) {
            $all_unread_count--;
        }

        $response = [
            'data' => $notification_detail,
            'all_unread_count' => $all_unread_count,
        ];
        
        return json_custom_response($response);
    }
}
