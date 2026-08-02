<?php

namespace App\Mail;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Bienvenida para un cliente de entrenamiento personal 1:1
 * (is_personal_client=true) — acceso completo, tono distinto al de un
 * cliente free. Ver Services\WelcomeMailService.
 */
class WelcomePersonalClientMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function build()
    {
        $app_setting = AppSetting::first();

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('¡Bienvenido a tu entrenamiento personal en ' . config('app.name') . '!')
            ->markdown('emails.sendmail')
            ->with([
                'greeting'    => '¡Hola, ' . $this->user->first_name . '!',
                'level'       => 'success',
                'introLines'  => [
                    'Tu cuenta ya está lista, con acceso completo a todo el contenido de entrenamiento y nutrición que tu coach te vaya asignando — sin necesidad de comprar nada.',
                ],
                'outroLines'  => [
                    'Tu coach se pondrá en contacto contigo en breve para empezar a diseñar tu plan.',
                    '',
                    'Un saludo,',
                ],
                'salutation'  => config('app.name'),
                'app_settings' => $app_setting,
            ]);
    }
}
