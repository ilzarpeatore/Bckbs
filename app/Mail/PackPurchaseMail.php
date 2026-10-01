<?php

namespace App\Mail;

use App\Models\PackPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmación de compra de un pack en la web: cómo recibirlo en la app
 * (registrarse con este email) y el código de canje por si usa otro email.
 * Ver App\Services\PackPurchaseService.
 */
class PackPurchaseMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PackPurchase $purchase)
    {
    }

    public function build()
    {
        $plan = $this->purchase->plan;
        $stores = array_filter([
            config('services.packs.app_store_url') ? 'App Store: ' . config('services.packs.app_store_url') : null,
            config('services.packs.play_store_url') ? 'Google Play: ' . config('services.packs.play_store_url') : null,
        ]);

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject("Tu pack {$plan->name} está listo")
            ->markdown('emails.sendmail')
            ->with([
                'greeting' => $this->purchase->customer_name ? "¡Hola, {$this->purchase->customer_name}!" : '¡Hola!',
                'level' => 'success',
                'introLines' => array_values(array_filter([
                    "Gracias por tu compra. Tu pack «{$plan->name}» te espera en la app de " . config('app.name') . '.',
                    'Para recibirlo, descarga la app y regístrate con este mismo email: ' . $this->purchase->email . '. Se activará solo al terminar el cuestionario inicial.',
                    $stores ? implode(' · ', $stores) : null,
                ])),
                'outroLines' => [
                    "¿Te has registrado o prefieres usar otro email? En la app ve a Perfil → Tengo un código e introduce: {$this->purchase->redeem_code}",
                    'Si tienes cualquier duda, responde a este email.',
                ],
            ]);
    }
}
