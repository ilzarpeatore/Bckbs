<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

/**
 * Correo de aviso al equipo (admin/coach) para eventos operativos.
 *
 * Existe porque los push a admin/coach no llegan a nadie (ningún admin tiene
 * expo_push_token/player_id) y el Panel de Excepciones solo se ve al abrir el
 * panel. Destinatarios: STAFF_ALERT_EMAILS (config mail.staff_alerts).
 *
 * Nunca lanza: un correo fallido (SMTP sin configurar, servidor caído) no debe
 * romper el cierre de una sesión ni el scheduler. Devuelve si se llegó a enviar.
 */
class StaffAlertService
{
    public static function send(string $subject, string $body): bool
    {
        $to = config('mail.staff_alerts', []);
        if (empty($to)) {
            return false;
        }

        // Sin remitente configurado el SMTP lo rechaza; mejor no intentarlo.
        if (empty(config('mail.from.address'))) {
            report(new \RuntimeException('StaffAlertService: MAIL_FROM_ADDRESS vacío, correo de aviso no enviado.'));
            return false;
        }

        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject('[Be Stronger] ' . $subject);
            });

            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    public static function adminUrl(string $path = ''): string
    {
        return rtrim((string) config('mail.admin_panel_url'), '/') . $path;
    }
}
