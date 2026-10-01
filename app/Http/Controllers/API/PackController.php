<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PackPurchase;
use App\Models\Plan;
use App\Services\PackPurchaseService;
use App\Services\Stripe\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Packs vendidos en la web (docs/PACKS_WEB.md).
 *
 * Públicos (los usa la web, sin cuenta): catálogo, detalle, crear el pago en
 * Stripe y estado del pago para la página de gracias.
 * Con sesión (los usa la app): canjear un código. La app no muestra precios
 * ni enlaces de compra (normas de las tiendas, guideline 3.1.1).
 */
class PackController extends Controller
{
    public function catalog()
    {
        $plans = Plan::active()->where('is_pack', true)->where('sold_on_web', true)->orderBy('sort_order')->orderBy('id')->get();

        return json_custom_response(['data' => $plans->map(fn (Plan $plan) => $this->present($plan))->values()]);
    }

    public function detail(Request $request)
    {
        $request->validate(['slug' => 'required|string']);
        $plan = Plan::active()->where('is_pack', true)->where('sold_on_web', true)->where('slug', $request->slug)->first();
        if (!$plan) {
            return json_message_response('Pack no encontrado.', 404);
        }

        return json_custom_response(['data' => $this->present($plan)]);
    }

    public function checkout(Request $request, StripeGateway $stripe)
    {
        $request->validate([
            'slug' => 'required|string',
            'email' => 'nullable|email|max:255',
        ]);

        $plan = Plan::active()->where('is_pack', true)->where('sold_on_web', true)->where('slug', $request->slug)->first();
        if (!$plan) {
            return json_message_response('Pack no encontrado.', 404);
        }
        if (!$stripe->isConfigured()) {
            Log::error('Packs: checkout pedido sin STRIPE_SECRET_KEY configurada.');
            return json_message_response('El pago no está disponible en este momento.', 503);
        }

        $web = config('services.packs.web_url');
        $session = $stripe->createCheckoutSession(
            $plan,
            $web . '/packs/gracias?session_id={CHECKOUT_SESSION_ID}',
            $web . '/packs/' . $plan->slug,
            $request->email ? PackPurchase::normalizeEmail($request->email) : null,
        );

        return json_custom_response(['data' => ['url' => $session->url]]);
    }

    /**
     * Página de gracias: dice con qué email registrarse. Si el webhook aún no
     * ha llegado, consulta la sesión a Stripe y registra la compra aquí
     * (idempotente: el webhook la encontrará ya hecha).
     */
    public function checkoutStatus(Request $request, StripeGateway $stripe)
    {
        $request->validate(['session_id' => 'required|string|max:255']);

        $purchase = PackPurchase::where('stripe_session_id', $request->session_id)->first();

        if (!$purchase && $stripe->isConfigured()) {
            try {
                $session = $stripe->retrieveCheckoutSession($request->session_id);
                $purchase = PackPurchaseService::recordPaidSession($session);
                if (!$purchase) {
                    return json_custom_response(['data' => ['status' => 'pending']]);
                }
            } catch (\Throwable $e) {
                return json_message_response('Pago no encontrado.', 404);
            }
        }
        if (!$purchase) {
            return json_message_response('Pago no encontrado.', 404);
        }

        return json_custom_response(['data' => [
            'status' => $purchase->status === PackPurchase::STATUS_REFUNDED ? 'refunded' : 'paid',
            'email' => $purchase->email,
            'pack' => $purchase->plan?->name,
            'redeem_code' => $purchase->redeem_code,
            'already_linked' => $purchase->status === PackPurchase::STATUS_CLAIMED,
        ]]);
    }

    public function redeem(Request $request)
    {
        $request->validate(['code' => 'required|string|max:40']);

        $result = PackPurchaseService::redeem(auth('sanctum')->user(), $request->code);
        if (!$result['ok']) {
            return json_message_response($result['message'], 422);
        }

        $purchase = $result['purchase'];

        return json_custom_response([
            'message' => $result['message'],
            'data' => [
                'pack' => $purchase->plan?->name,
                // true = ya asignado; false = se asignará al terminar el cuestionario inicial.
                'started' => (bool) $purchase->subscription?->fulfilled_at,
            ],
        ]);
    }

    private function present(Plan $plan): array
    {
        return [
            'slug' => $plan->slug,
            'name' => $plan->name,
            'short_description' => $plan->short_description,
            'description' => $plan->description,
            'image_url' => $plan->image_url,
            'price' => (float) $plan->price + (float) $plan->signup_fee,
            'currency' => $plan->currency ?: 'EUR',
            'duration' => (int) $plan->invoice_period,
            'duration_unit' => $plan->invoice_interval,
            'includes' => array_values(array_filter([
                $plan->training_program_id ? 'training' : null,
                $plan->meal_plan_template_id ? 'nutrition' : null,
                !empty($plan->habit_template_ids) ? 'habits' : null,
                !empty($plan->resource_ids) ? 'resources' : null,
            ])),
        ];
    }
}
