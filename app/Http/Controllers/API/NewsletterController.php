<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterConfirmMail;
use App\Models\NewsletterSubscriber;
use App\Support\Attribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Newsletter y lista de espera de la web, con doble opt-in (RGPD): el alta
 * queda "pending" hasta que se confirma desde el email. Ver docs/MARKETING_WEB.md.
 */
class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $request->validate([
            'email' => 'required|email:rfc|max:190',
            'source' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9:_-]+$/'],
        ]);

        // Trampa para bots: campo invisible que una persona deja vacío.
        if ($request->filled('website')) {
            return json_custom_response(['data' => ['status' => NewsletterSubscriber::STATUS_PENDING]]);
        }

        $email = mb_strtolower(trim($request->email));
        $subscriber = NewsletterSubscriber::where('email', $email)->first();

        if ($subscriber?->status === NewsletterSubscriber::STATUS_CONFIRMED) {
            return json_custom_response(['data' => ['status' => NewsletterSubscriber::STATUS_CONFIRMED]]);
        }

        if (!$subscriber) {
            $subscriber = NewsletterSubscriber::create(array_merge(Attribution::fromRequest($request), [
                'email' => $email,
                'source' => $request->source ?: 'footer',
                'status' => NewsletterSubscriber::STATUS_PENDING,
                'confirm_token' => NewsletterSubscriber::newToken(),
                'unsubscribe_token' => NewsletterSubscriber::newToken(),
            ]));
        } elseif ($subscriber->status === NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
            $subscriber->update([
                'status' => NewsletterSubscriber::STATUS_PENDING,
                'source' => $request->source ?: $subscriber->source,
                'confirm_token' => NewsletterSubscriber::newToken(),
                'unsubscribe_token' => NewsletterSubscriber::newToken(),
                'unsubscribed_at' => null,
                'confirmation_sent_at' => null,
            ]);
        }

        // Pendiente: (re)envía la confirmación, como mucho cada 10 minutos.
        if (!$subscriber->confirmation_sent_at || $subscriber->confirmation_sent_at->lt(now()->subMinutes(10))) {
            try {
                Mail::to($subscriber->email)->send(new NewsletterConfirmMail($subscriber));
                $subscriber->update(['confirmation_sent_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning("Newsletter: no se pudo enviar la confirmación a {$subscriber->id}", ['error' => $e->getMessage()]);
            }
        }

        return json_custom_response(['data' => ['status' => NewsletterSubscriber::STATUS_PENDING]]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['token' => 'required|string|max:64']);
        $subscriber = NewsletterSubscriber::where('confirm_token', $request->token)->first();
        if (!$subscriber) {
            return json_message_response('Enlace no válido o caducado.', 404);
        }

        if ($subscriber->status === NewsletterSubscriber::STATUS_PENDING) {
            $subscriber->update(['status' => NewsletterSubscriber::STATUS_CONFIRMED, 'confirmed_at' => now()]);
        }

        return json_custom_response(['data' => [
            'status' => $subscriber->status,
            'source' => $subscriber->source,
            'unsubscribe_token' => $subscriber->unsubscribe_token,
        ]]);
    }

    public function unsubscribe(Request $request)
    {
        $request->validate(['token' => 'required|string|max:64']);
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $request->token)->first();
        if (!$subscriber) {
            return json_message_response('Enlace no válido.', 404);
        }

        if ($subscriber->status !== NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
            $subscriber->update(['status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => now()]);
        }

        return json_custom_response(['data' => ['status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED]]);
    }
}
