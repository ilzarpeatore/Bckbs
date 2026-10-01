<?php

namespace App\Mail;

use App\Models\PackPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Recordatorio a quien compró un pack en la web y aún no lo tiene en la app
 * (no se ha registrado con ese email ni ha canjeado el código). Lo envía
 * packs:remind-unclaimed. Ver docs/PACKS_WEB.md.
 */
class PackReminderMail extends Mailable implements ShouldQueue
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
            ->subject("Tu pack {$plan->name} te está esperando")
            ->markdown('emails.sendmail')
            ->with([
                'greeting' => $this->purchase->customer_name ? "¡Hola, {$this->purchase->customer_name}!" : '¡Hola!',
                'level' => 'success',
                'introLines' => array_values(array_filter([
                    "Hace unos días compraste el pack «{$plan->name}», pero todavía no lo has activado en la app de " . config('app.name') . '.',
                    'Solo tienes que descargar la app y registrarte con este email: ' . $this->purchase->email . '. Al terminar el cuestionario inicial, tu pack aparecerá en tu calendario.',
                    $stores ? implode(' · ', $stores) : null,
                ])),
                'outroLines' => [
                    "¿Te registraste con otro email? En la app ve a Perfil → Tengo un código e introduce: {$this->purchase->redeem_code}",
                    'Si tienes cualquier problema, responde a este email y te ayudamos.',
                ],
            ]);
    }
}
