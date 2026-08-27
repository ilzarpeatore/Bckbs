<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Subscription;
use App\Services\PackageFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * Checkout de Packages (suscripciones recurrentes Y programas individuales de
 * pago único -- ver docs/PLAN_VENTAS_PROGRAMAS_Y_BLOG.md en el repo bsa) desde
 * la web (webbs), fuera de la app -- las tiendas de Apple/Google no permiten
 * vender contenido digital dentro de la app (ver api/subscription.ts en bsa).
 *
 * El punto de enganche real con el resto del sistema es Subscription::boot()
 * (evento `saved`): en cuanto se crea aquí una Subscription con
 * status=ACTIVE + payment_status=paid, PackageFulfillmentService importa el
 * contenido del Package (TrainingProgram/MealPlanTemplate) al calendario real
 * del cliente, automáticamente -- este controlador NO llama a ese servicio
 * directamente, solo crea la fila correcta (mismo patrón que ya usa
 * SubscriptionController::subscribeToPackage(), pensado para esto desde su
 * propio comentario: "gateway de pago real más adelante").
 */
class CheckoutController extends Controller
{
    /**
     * Crea una Stripe Checkout Session para un Package. Una única sesión con
     * los 3 métodos (tarjeta, Bizum, Link) -- Stripe muestra las opciones en
     * su propia UI de checkout, el cliente elige una, no hace falta construir
     * flujos separados por método.
     */
    public function createStripeSession(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $user = auth()->user();
        $package = Package::where('status', 'active')->findOrFail($validated['package_id']);

        $stripe = new StripeClient(config('services.stripe.secret'));

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card', 'bizum', 'link'],
            'customer_email' => $user->email,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $package->name,
                        'description' => $package->description,
                    ],
                    // Stripe trabaja en céntimos, no en euros.
                    'unit_amount' => (int) round($package->price * 100),
                ],
                'quantity' => 1,
            ]],
            // metadata viaja con el evento del webhook -- es como sabemos, al
            // recibir la confirmación de pago, a qué usuario y qué Package
            // corresponde (Stripe no conoce nuestro modelo de datos).
            'metadata' => [
                'user_id' => (string) $user->id,
                'package_id' => (string) $package->id,
            ],
            'success_url' => rtrim(config('services.frontend_url'), '/') . '/checkout/exito?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => rtrim(config('services.frontend_url'), '/') . '/checkout/cancelado',
        ]);

        return json_custom_response([
            'checkout_url' => $session->url,
        ]);
    }

    /**
     * Webhook de Stripe -- ruta pública (sin auth:sanctum, ver routes/api.php),
     * verificada por firma (Stripe-Signature) en vez de por token, porque la
     * llama Stripe directamente, no un cliente logueado.
     */
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            Log::warning('Stripe webhook: firma inválida o payload malformado', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $this->fulfillStripeSession($event->data->object);
        }

        return response()->json(['received' => true]);
    }

    private function fulfillStripeSession(\Stripe\Checkout\Session $session): void
    {
        // Idempotencia: Stripe puede reenviar el mismo evento más de una vez
        // (reintentos si no respondemos 2xx a tiempo) -- sin esto se
        // duplicaría la Subscription y se re-importaría el contenido.
        if (Subscription::where('txn_id', $session->id)->exists()) {
            return;
        }

        $userId = $session->metadata->user_id ?? null;
        $packageId = $session->metadata->package_id ?? null;

        if (!$userId || !$packageId) {
            Log::warning('Stripe checkout.session.completed sin metadata esperada', ['session_id' => $session->id]);
            return;
        }

        $package = Package::find($packageId);
        if (!$package) {
            Log::warning('Stripe checkout.session.completed: package no encontrado', ['package_id' => $packageId, 'session_id' => $session->id]);
            return;
        }

        // payment_method_types en el evento refleja el método REAL usado por
        // el cliente (no la lista completa que se ofreció al crear la
        // sesión) -- de ahí sale si pagó con tarjeta, Bizum o Link.
        $paymentMethodType = $session->payment_method_types[0] ?? 'card';

        $start = now()->startOfDay();
        $end = PackageFulfillmentService::computeEndDate($package, $start);

        Subscription::create([
            'user_id'                 => $userId,
            'package_id'              => $package->id,
            'total_amount'            => $package->price,
            'payment_type'            => 'stripe_' . $paymentMethodType,
            'txn_id'                  => $session->id,
            'transaction_detail'      => $session->toArray(),
            'payment_status'          => 'paid',
            'status'                  => config('constant.SUBSCRIPTION_STATUS.ACTIVE'),
            'subscription_start_date' => $start,
            'subscription_end_date'   => $end,
        ]);
        // Al guardarse, Subscription::boot() dispara PackageFulfillmentService
        // automáticamente -- no hace falta llamarlo aquí.
    }
}
