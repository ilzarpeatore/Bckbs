<?php

namespace App\Mail;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Bienvenida para un cliente free (catálogo público, puede comprar Packages).
 * Distinta de WelcomePersonalClientMail — ver Services\WelcomeMailService.
 */
class WelcomeFreeClientMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function build()
    {
        $app_setting = AppSetting::first();

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('¡Bienvenido a ' . config('app.name') . '!')
            ->markdown('emails.sendmail')
            ->with([
                'greeting'    => '¡Hola, ' . $this->user->first_name . '!',
                'level'       => 'success',
                'introLines'  => [
                    'Tu cuenta ya está lista. Explora el catálogo de rutinas y recetas, y cuando quieras un programa guiado de entrenamiento o nutrición, tenemos paquetes disponibles para comprar dentro de la app.',
                ],
                'outroLines'  => [
                    'Un saludo,',
                ],
                'salutation'  => config('app.name'),
                'app_settings' => $app_setting,
            ]);
    }
}
