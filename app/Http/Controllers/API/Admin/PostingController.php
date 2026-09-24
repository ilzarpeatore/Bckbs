<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Posting;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Http\Resources\PostingResource;
use App\Http\Resources\CommentResource;
use Illuminate\Http\Request;
use App\Support\FuzzySearch;

class PostingController extends BaseController
{
    protected function getModelClass(): string
    {
        return Posting::class;
    }

    protected function getResourceClass(): string
    {
        return PostingResource::class;
    }

    public function index(Request $request)
    {
        $query = Posting::with(['user']);

        if ($request->filled('search')) {
            FuzzySearch::apply($query, ['description'], $request->search);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        $items = $query->orderBy('id', 'desc')->paginate($perPage);
        $items = PostingResource::collection($items);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    public function reportList(Request $request)
    {
        $query = Posting::has('reportPosting')->with(['user', 'reportPosting']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        $items = $query->orderBy('id', 'desc')->paginate($perPage);
        $items = PostingResource::collection($items);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    /**
     * Listado de comentarios reportados para el panel admin -- mismo patrón
     * que reportList() (posts), item 11 del roadmap. Se resuelve el usuario
     * y el propio comentario para que el panel pueda mostrar de qué post
     * es y quién lo escribió.
     */
    public function reportedComments(Request $request)
    {
        $query = Comment::has('reportComment')->with(['user', 'posting', 'reportComment']);

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        $items = $query->orderBy('id', 'desc')->paginate($perPage);
        $items = CommentResource::collection($items);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    public function updateStatus(Request $request, $id)
    {
        $posting = Posting::find($id);

        if (!$posting) {
            return json_message_response('Posting not found.', 404);
        }

        $request->validate([
            'status' => 'required|in:active,inactive,banned',
        ]);

        $posting->update(['status' => $request->status]);

        return json_custom_response([
            'message' => 'Posting status updated.',
            'data'    => $posting,
        ]);
    }

    /**
     * Borrado moderador: elimina un post reportado sin importar quién sea
     * el dueño. Solo alcanzable vía admin (middleware admin.api en el grupo
     * de rutas), a diferencia de PostingController::deletePostdata que es
     * el borrado del propio usuario (o de un admin actuando como tal).
     */
    public function destroyReported(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:postings,id',
        ]);

        $posting = Posting::find($request->id);

        $posting->delete();

        return json_message_response('Posting deleted.');
    }

    /**
     * Moderacion de comentarios como staff (item 6, auditoria de migracion
     * 2026-09-11). Reutiliza el mismo scope Comment::canBeDeletedBy() que
     * ya usa CommunityController (Blade) -- ese scope YA permite borrar
     * cualquier comentario cuando el usuario autenticado hasRole('admin'),
     * asi que no hace falta logica nueva, solo exponerlo via /admin.
     */
    public function destroyComment(Request $request, $id)
    {
        $comment = Comment::canBeDeletedBy()->where('id', $id)->first();

        if (!$comment) {
            return json_message_response('Comentario no encontrado.', 404);
        }

        $comment->delete();

        return json_message_response('Comentario eliminado.');
    }

    public function destroyCommentReply(Request $request, $id)
    {
        $reply = CommentReply::canBeDeletedBy()->where('id', $id)->first();

        if (!$reply) {
            return json_message_response('Respuesta no encontrada.', 404);
        }

        $reply->delete();

        return json_message_response('Respuesta eliminada.');
    }
}
