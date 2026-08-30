<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Posting;
use App\Http\Resources\PostingResource;
use Illuminate\Http\Request;

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
            $search = $request->search;
            $query->where('description', 'LIKE', "%{$search}%");
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
}
