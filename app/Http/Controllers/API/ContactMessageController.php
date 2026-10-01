<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Support\Attribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Formulario de contacto de la web: se guarda y se avisa por email. */
class ContactMessageController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:190',
            'subject' => 'nullable|string|max:190',
            'message' => 'required|string|min:5|max:5000',
        ]);

        if ($request->filled('website')) {
            return json_message_response('Mensaje enviado.');
        }

        $contact = ContactMessage::create(array_merge(Attribution::fromRequest($request), [
            'name' => strip_tags($request->name),
            'email' => mb_strtolower(trim($request->email)),
            'subject' => $request->subject ? strip_tags($request->subject) : null,
            'message' => strip_tags($request->message),
            'status' => 'new',
        ]));

        $to = config('services.web.contact_notify_email') ?: config('mail.from.address');
        if ($to) {
            try {
                Mail::to($to)->send(new ContactMessageMail($contact));
            } catch (\Throwable $e) {
                Log::warning("Contacto: no se pudo enviar el aviso del mensaje {$contact->id}", ['error' => $e->getMessage()]);
            }
        }

        return json_message_response('Mensaje enviado.');
    }
}
