<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppFeedback;
use Illuminate\Http\Request;

class AppFeedbackController extends Controller
{
    public function getList(Request $request)
    {
        $query = AppFeedback::with(['user:id,first_name,last_name,email']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('section')) {
            $query->where('section', $request->section);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        $feedback = $query->orderByDesc('created_at')->paginate($perPage);

        $response = [
            'pagination' => json_pagination_response($feedback),
            'data'       => $feedback,
        ];

        return json_custom_response($response);
    }

    public function getDetail(Request $request)
    {
        $request->validate(['id' => 'required|exists:app_feedback,id']);

        $feedback = AppFeedback::with(['user:id,first_name,last_name,email'])
            ->findOrFail($request->id);

        return json_custom_response(['data' => $feedback]);
    }

    /**
     * Permite al admin marcar el feedback como reviewed/closed (o
     * devolverlo a open).
     */
    public function update(Request $request)
    {
        $request->validate([
            'id'     => 'required|exists:app_feedback,id',
            'status' => 'required|in:open,reviewed,closed',
        ]);

        $feedback = AppFeedback::findOrFail($request->id);
        $feedback->update(['status' => $request->status]);

        return json_custom_response(['data' => $feedback, 'message' => 'App feedback updated.']);
    }
}
