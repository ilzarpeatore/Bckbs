<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $introLines = [
            'Tu suscripción de **' . ($this->data['plan_name'] ?? 'tu suscripción') . '**'
                . ($this->data['ends_at'] ? ' expira el **' . $this->data['ends_at'] . '**' : ' está a punto de expirar')
                . ($this->data['days_left'] !== null ? ' (' . $this->data['days_left'] . ' días restantes).' : '.'),
            'Renueva tu plan para no perder el acceso a tus entrenamientos y tu plan de nutrición.',
        ];

        $outroLines = [
            __('message.thank_you'),
        ];

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Tu suscripción está a punto de expirar')
            ->markdown('emails.sendmail')
            ->with([
                'greeting' => 'Hola ' . ($this->data['subscriber_name'] ?? '') . ',',
                'level' => 'success',
                'introLines' => $introLines,
                'outroLines' => $outroLines,
                'salutation' => "Un saludo,\n" . config('app.name'),
            ]);
    }
}
