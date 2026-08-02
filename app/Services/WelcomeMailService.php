<?php

namespace App\Services;

use App\Models\User;
use App\Mail\WelcomeFreeClientMail;
use App\Mail\WelcomePersonalClientMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Punto único de disparo del correo de bienvenida — cliente normal (free)
 * recibe una plantilla, cliente de entrenamiento personal 1:1 recibe otra.
 * Se llama tanto si el cliente se registra solo (API\UserController::register)
 * como si el coach lo crea a mano desde el admin
 * (Admin\UserController::store). Deliberadamente sin fallar el registro si
 * el envío falla (ej. no hay proveedor de email real configurado todavía) —
 * ver TAREAS.md, "preparado pero sin proveedor configurado".
 */
class WelcomeMailService
{
    public static function sendFor(User $user): void
    {
        $isPersonalClient = (bool) $user->is_personal_client;
        $email = $user->email;
        $userId = $user->id;

        app()->terminating(function () use ($user, $isPersonalClient, $email, $userId) {
            try {
                $mailable = $isPersonalClient
                    ? new WelcomePersonalClientMail($user)
                    : new WelcomeFreeClientMail($user);

                Mail::to($email)->send($mailable);
            } catch (\Throwable $e) {
                Log::warning('WelcomeMailService: failed to send welcome email for user ' . $userId, [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
