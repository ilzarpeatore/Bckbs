<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Doble opt-in de la newsletter: el alta no cuenta hasta que se confirma. */
class NewsletterConfirmMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public NewsletterSubscriber $subscriber)
    {
    }

    public function build()
    {
        $web = config('services.packs.web_url');
        $isWaitlist = $this->subscriber->source === 'waitlist';

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($isWaitlist ? 'Confirma tu email para la lista de espera' : 'Confirma tu suscripción a ' . config('app.name'))
            ->markdown('emails.action')
            ->with([
                'greeting' => '¡Hola!',
                'introLines' => [
                    $isWaitlist
                        ? 'Gracias por apuntarte a la lista de espera. Confirma tu email y te avisaremos en cuanto la app esté disponible.'
                        : 'Gracias por suscribirte. Confirma tu email para empezar a recibir consejos sobre entrenamiento, nutrición y constancia.',
                ],
                'actionText' => 'Confirmar mi email',
                'actionUrl' => "{$web}/newsletter/confirmar?token={$this->subscriber->confirm_token}",
                'outroLines' => ['Si no te has apuntado tú, ignora este mensaje: no te enviaremos nada más.'],
                'footerNote' => null,
            ]);
    }
}
