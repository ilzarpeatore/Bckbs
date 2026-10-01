<?php

namespace App\Mail;

use App\Models\PackCheckoutAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Cesta abandonada: quien empezó a pagar un pack y no terminó, y aceptó en
 * Stripe recibir comunicaciones (consent_collection), recibe un único email
 * con el enlace de Stripe para retomar el pago. Ver docs/MARKETING_WEB.md.
 */
class PackRecoveryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PackCheckoutAttempt $attempt)
    {
    }

    public function build()
    {
        $plan = $this->attempt->plan;

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject("¿Tuviste algún problema con tu pack {$plan->name}?")
            ->markdown('emails.action')
            ->with([
                'greeting' => '¡Hola!',
                'introLines' => [
                    "Empezaste a comprar el pack «{$plan->name}» pero el pago no llegó a completarse.",
                    'Si fue un problema con la tarjeta o simplemente te interrumpieron, puedes retomarlo donde lo dejaste:',
                ],
                'actionText' => 'Retomar mi compra',
                'actionUrl' => $this->attempt->recovery_url,
                'outroLines' => ['¿Tienes alguna duda sobre el programa? Responde a este email y te ayudamos.'],
                'footerNote' => 'Recibes este email porque aceptaste recibir comunicaciones de ' . config('app.name') . ' al empezar la compra. No te enviaremos más recordatorios sobre esta compra.',
            ]);
    }
}
