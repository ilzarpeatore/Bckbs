<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ScreenReviewMark;

/**
 * Herramienta temporal de desarrollo — FAB en cada pantalla de la app para
 * marcar "borrar"/"terminada"/"no entiendo" + nota libre. Se borrara junto
 * con el resto del feature (migracion, modelo, FAB, rutas) cuando ya no
 * haga falta. Ver ScreenReviewFab.tsx en la app para el contexto completo.
 */
class ScreenReviewMarkController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'route_name' => 'required|string|max:255',
            'status'     => 'required|in:delete,done,confused',
            'note'       => 'nullable|string',
        ]);

        // updateOrCreate (una marca VIGENTE por pantalla, no un log que
        // acumula filas) — corrige el bug reportado: al reabrir el FAB en
        // la misma pantalla se recupera la nota ya guardada en vez de
        // parecer que se perdio. Tambien hace trivial la vista "que
        // pantallas tienen nota / cuales para borrar / cuales terminadas":
        // cada pantalla aparece una sola vez con su estado actual.
        $mark = ScreenReviewMark::updateOrCreate(
            ['user_id' => auth('sanctum')->id(), 'route_name' => $request->route_name],
            ['status' => $request->status, 'note' => $request->note]
        );

        return json_custom_response(['data' => $mark]);
    }

    /** Listado para revisar las marcas — filtrable por status (?status=delete) y/o route_name. */
    public function index(Request $request)
    {
        $query = ScreenReviewMark::orderByDesc('updated_at');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('route_name')) {
            $query->where('route_name', $request->route_name);
        }
        return json_custom_response(['data' => $query->get()]);
    }
}
