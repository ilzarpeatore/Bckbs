<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\PersonalClientInvite;
use App\Http\Resources\PersonalClientInviteResource;
use Illuminate\Http\Request;

/**
 * Códigos de invitación para registrar clientes de entrenamiento personal
 * 1:1 (Niveles de acceso, 2026-07-30). El coach genera un código desde el
 * admin y lo comparte a mano (WhatsApp, SMS...); el cliente lo introduce al
 * registrarse en la app y su cuenta queda is_personal_client=true
 * automáticamente. Ver App\Http\Controllers\API\UserController::register().
 */
class PersonalClientInviteController extends Controller
{
    public function index(Request $request)
    {
        $query = PersonalClientInvite::with('usedBy')->orderBy('id', 'desc');

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        if ($perPage == -1 || $perPage > 250) {
            $perPage = 250;
        }

        $items = $query->paginate($perPage);

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data'       => PersonalClientInviteResource::collection($items),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'email'      => 'nullable|email|max:191',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

        $invite = PersonalClientInvite::create([
            'code'               => PersonalClientInvite::generateUniqueCode(),
            'first_name'         => $validated['first_name'] ?? null,
            'last_name'          => $validated['last_name'] ?? null,
            'email'              => $validated['email'] ?? null,
            'created_by_user_id' => auth()->id(),
            'expires_at'         => now()->addDays($validated['expires_in_days'] ?? 30),
        ]);

        return json_custom_response([
            'message' => 'Invitation code created.',
            'data'    => new PersonalClientInviteResource($invite),
        ], 201);
    }

    public function destroy($id)
    {
        $invite = PersonalClientInvite::find($id);

        if (!$invite) {
            return json_message_response('Invite not found.', 404);
        }

        if ($invite->isUsed()) {
            return json_message_response('Cannot revoke an invite that has already been used.', 400);
        }

        $invite->delete();

        return json_message_response('Invite revoked.');
    }
}
