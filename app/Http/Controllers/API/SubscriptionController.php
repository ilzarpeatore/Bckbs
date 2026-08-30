<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanSubscriptionResource;
use App\Models\PlanSubscription;

/**
 * RETIRADO 2026-08-13: este controller antes tenía el autoservicio de compra
 * dentro de la app (getList/subscriptionSave/subscribeToPackage/cancelSubscription,
 * sistema Package/Subscription legacy) — quitado junto con las pantallas de
 * compra de la app (Apple/Google exigen que una app no venda contenido digital
 * dentro sin su propio IAP; la compra pasa a ser 100% externa, en la web).
 * El acceso real ahora se concede vía Plan/PlanSubscription
 * (PlanSubscriptionController::grantPlan() en el admin, o el webhook de Stripe) —
 * este controller queda solo con la vista de solo lectura que necesita la app.
 */
class SubscriptionController extends Controller
{
    /**
     * "Mi plan" — estado de solo lectura para la app (MigratedSubscriptionDetail).
     * Sin ninguna acción de escritura: cambiar/cancelar un plan pasa a ser cosa
     * del coach (panel admin), no autoservicio.
     */
    public function myPlan()
    {
        $user = auth()->user();

        $active = $user->activePlanSubscriptions()->with('plan')->latest('starts_at')->first();

        $history = PlanSubscription::where('subscriber_type', get_class($user))
            ->where('subscriber_id', $user->id)
            ->with('plan')
            ->orderByDesc('created_at')
            ->get();

        return json_custom_response([
            'data' => [
                'active'  => $active ? new PlanSubscriptionResource($active) : null,
                'history' => PlanSubscriptionResource::collection($history),
            ],
        ]);
    }
}
