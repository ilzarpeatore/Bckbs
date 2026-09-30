<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackPurchase;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PackPurchaseService;
use Illuminate\Http\Request;

/**
 * Compras de packs hechas en la web (docs/PACKS_WEB.md): listado, reenviar
 * el email con el código y vincular a mano una compra a un cliente (p. ej.
 * pagó con un email y se registró con otro y no encuentra el código).
 */
class PackPurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = PackPurchase::with(['plan:id,name,slug', 'user:id,first_name,last_name,email'])->latest();

        if ($request->filled('search')) {
            $search = PackPurchase::normalizeEmail($request->search);
            $query->where(fn ($q) => $q->where('email', 'like', "%{$search}%")
                ->orWhere('redeem_code', PackPurchase::normalizeCode($request->search)));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $items = $query->paginate((int) $request->get('per_page', 25));

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data' => $items->map(fn (PackPurchase $p) => [
                'id' => $p->id,
                'plan' => $p->plan ? ['id' => $p->plan->id, 'name' => $p->plan->name] : null,
                'email' => $p->email,
                'customer_name' => $p->customer_name,
                'amount' => $p->amount_cents / 100,
                'currency' => $p->currency,
                'status' => $p->status,
                'redeem_code' => $p->redeem_code,
                'user' => $p->user ? [
                    'id' => $p->user->id,
                    'name' => trim("{$p->user->first_name} {$p->user->last_name}"),
                    'email' => $p->user->email,
                ] : null,
                'started' => (bool) $p->subscription?->fulfilled_at,
                'claimed_at' => $p->claimed_at?->toISOString(),
                'created_at' => $p->created_at?->toISOString(),
            ]),
        ]);
    }

    public function resend(Request $request)
    {
        $request->validate(['id' => 'required|exists:pack_purchases,id']);
        $purchase = PackPurchase::findOrFail($request->id);
        PackPurchaseService::sendConfirmation($purchase);

        return json_message_response('Email reenviado.');
    }

    public function link(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:pack_purchases,id',
            'user_id' => 'required|exists:users,id',
        ]);
        $purchase = PackPurchase::findOrFail($request->id);
        if ($purchase->status !== PackPurchase::STATUS_PAID) {
            return json_message_response('Esta compra ya está vinculada o devuelta.', 422);
        }

        PackPurchaseService::claim($purchase, User::findOrFail($request->user_id));
        AuditLogger::log('link_pack_purchase', 'pack_purchases', $purchase->id, "Compra vinculada a mano al usuario {$request->user_id}.");

        return json_message_response('Compra vinculada.');
    }
}
