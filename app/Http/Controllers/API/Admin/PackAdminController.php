<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackPurchase;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Herramientas de la página Packs del panel (docs/PACKS_WEB.md): subir la
 * imagen del pack y estadísticas de ventas por pack.
 */
class PackAdminController extends Controller
{
    /** Sube la imagen del pack y devuelve su URL pública (se guarda luego en plans.image_url). */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $path = $request->file('image')->store('packs', 'public');

        return json_custom_response(['data' => ['url' => Storage::disk('public')->url($path)]]);
    }

    /**
     * Ventas por pack: compras (sin contar devoluciones), ingresos, cuántos
     * compradores ya tienen la compra en su cuenta de la app y cuántos aún no.
     */
    public function stats()
    {
        $rows = PackPurchase::query()
            ->selectRaw('plan_id, currency')
            ->selectRaw("SUM(CASE WHEN status <> 'refunded' THEN 1 ELSE 0 END) AS purchases")
            ->selectRaw("SUM(CASE WHEN status <> 'refunded' THEN amount_cents ELSE 0 END) AS revenue_cents")
            ->selectRaw("SUM(CASE WHEN status = 'claimed' THEN 1 ELSE 0 END) AS registered")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS not_registered")
            ->selectRaw("SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) AS refunded")
            ->selectRaw('MAX(created_at) AS last_purchase_at')
            ->groupBy('plan_id', 'currency')
            ->get();

        $plans = Plan::withTrashed()->whereIn('id', $rows->pluck('plan_id'))->pluck('name', 'id');

        $data = $rows->map(fn ($r) => [
            'plan_id' => (int) $r->plan_id,
            'plan_name' => $plans[$r->plan_id] ?? null,
            'currency' => $r->currency,
            'purchases' => (int) $r->purchases,
            'revenue' => ((int) $r->revenue_cents) / 100,
            'registered' => (int) $r->registered,
            'not_registered' => (int) $r->not_registered,
            'refunded' => (int) $r->refunded,
            'last_purchase_at' => $r->last_purchase_at ? \Illuminate\Support\Carbon::parse($r->last_purchase_at)->toISOString() : null,
        ])->values();

        return json_custom_response(['data' => $data]);
    }
}
