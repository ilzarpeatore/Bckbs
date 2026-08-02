<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Subscription;
use App\Models\Package;
use App\Http\Resources\SubscriptionResource;
use App\Services\PackageFulfillmentService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionController extends BaseController
{
    protected function getModelClass(): string
    {
        return Subscription::class;
    }

    protected function getResourceClass(): string
    {
        return SubscriptionResource::class;
    }

    public function index(Request $request)
    {
        $query = Subscription::with(['user', 'package']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        $items = $query->orderBy('id', 'desc')->paginate($perPage);
        $items = SubscriptionResource::collection($items);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    public function show($id)
    {
        $item = Subscription::with(['user', 'package'])->find($id);

        if (!$item) {
            return json_message_response('Subscription not found.', 404);
        }

        return json_custom_response(['data' => new SubscriptionResource($item)]);
    }

    /**
     * AÑADIDO 2026-07-30 — Fase 1 "Niveles de acceso": herramienta de admin para
     * conceder un Package a un cliente sin pasar por pago real (compra simulada).
     * Crea la Subscription ya activa+pagada, lo que dispara automáticamente
     * Subscription::boot()->saved() -> PackageFulfillmentService::fulfill(),
     * que importa el contenido (TrainingProgram/MealPlanTemplate) al calendario
     * real del cliente. Deliberadamente separado de
     * API\SubscriptionController::subscriptionSave() (el flujo de compra de
     * cliente, que asume una sola suscripción activa por usuario) — aquí sí se
     * permiten varias Subscriptions activas en paralelo, sin desactivar nada.
     */
    public function grantPackage(Request $request)
    {
        $validated = $request->validate([
            'user_id'                 => 'required|exists:users,id',
            'package_id'              => 'required|exists:packages,id',
            'subscription_start_date' => 'nullable|date',
        ]);

        $package = Package::findOrFail($validated['package_id']);
        $start = isset($validated['subscription_start_date'])
            ? Carbon::parse($validated['subscription_start_date'])->startOfDay()
            : now()->startOfDay();
        $end = PackageFulfillmentService::computeEndDate($package, $start);

        $subscription = Subscription::create([
            'user_id'                 => $validated['user_id'],
            'package_id'              => $package->id,
            'total_amount'            => $package->price,
            'payment_type'            => 'admin_grant',
            'payment_status'          => 'paid',
            'status'                  => config('constant.SUBSCRIPTION_STATUS.ACTIVE'),
            'subscription_start_date' => $start,
            'subscription_end_date'   => $end,
        ]);

        return json_custom_response([
            'message' => 'Package concedido e importado al calendario del cliente.',
            'data'    => new SubscriptionResource($subscription->fresh(['user', 'package'])),
        ], 201);
    }
}
