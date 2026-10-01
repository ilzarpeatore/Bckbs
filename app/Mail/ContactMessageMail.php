<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Aviso interno de un mensaje nuevo del formulario de contacto de la web. */
class ContactMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact)
    {
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo($this->contact->email, $this->contact->name)
            ->subject('Nuevo mensaje de contacto: ' . ($this->contact->subject ?: $this->contact->name))
            ->markdown('emails.action')
            ->with([
                'greeting' => 'Nuevo mensaje desde la web',
                'introLines' => array_values(array_filter([
                    "De: {$this->contact->name} <{$this->contact->email}>",
                    $this->contact->subject ? "Asunto: {$this->contact->subject}" : null,
                    $this->contact->message,
                    $this->contact->utm_campaign ? "Campaña: {$this->contact->utm_campaign}" : null,
                ])),
                'actionText' => null,
                'actionUrl' => null,
                'outroLines' => ['Responde a este email para contestarle directamente. También lo tienes en el panel → Mensajes de contacto.'],
                'footerNote' => null,
            ]);
    }
}
