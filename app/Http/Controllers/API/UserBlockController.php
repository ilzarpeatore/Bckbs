<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostingUserResource;
use App\Models\BlockedUser;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Bloqueo de usuario (item 11 del roadmap -- requisito para reactivar
 * COMMUNITY_ENABLED junto con el reporte de comentarios, ver
 * docs/PENDIENTE_BACKEND_ADMIN.md en el repo bsa). Un bloqueo oculta el
 * contenido de la otra persona en ambas direcciones (Posting/Comment
 * ::scopeExcludeBlockedUsers()) y evita comentar/dar like sobre el
 * contenido de la otra (ver los checks en PostingController/CommentController).
 */
class UserBlockController extends Controller
{
    public function block(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $blockerId = auth()->id();
        $blockedId = (int) $request->user_id;

        if ($blockedId === $blockerId) {
            return json_message_response('No puedes bloquearte a ti mismo.', 422);
        }

        BlockedUser::firstOrCreate([
            'blocker_id' => $blockerId,
            'blocked_id' => $blockedId,
        ]);

        return json_message_response('Usuario bloqueado.');
    }

    public function unblock(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        BlockedUser::where('blocker_id', auth()->id())
            ->where('blocked_id', (int) $request->user_id)
            ->delete();

        return json_message_response('Usuario desbloqueado.');
    }

    public function myBlockedUsers(Request $request)
    {
        $blockedIds = auth()->user()->blockedUsers()->pluck('blocked_id');
        $users = User::whereIn('id', $blockedIds)->get();

        return json_custom_response(['data' => PostingUserResource::collection($users)]);
    }
}
