<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use App\Support\FuzzySearch;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->filled('search')) {
            $userIds = FuzzySearch::matchingIds(\App\Models\User::class, ['first_name', 'last_name', 'email'], $request->search);
            $query->where(function ($q) use ($userIds, $request) {
                $q->where(FuzzySearch::likeClosure(['action', 'entity_type', 'detail'], $request->search))
                  ->orWhereIn('user_id', $userIds);
            });
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 50));
        $items = $query->orderBy('id', 'desc')->paginate($perPage);

        $data = $items->map(function (AuditLog $log) {
            return [
                'id' => $log->id,
                'user' => $log->user
                    ? [
                        'id' => $log->user->id,
                        'name' => $log->user->display_name ?? trim($log->user->first_name . ' ' . $log->user->last_name),
                        'email' => $log->user->email,
                    ]
                    : null,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'detail' => $log->detail,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toISOString(),
            ];
        });

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data' => $data,
        ]);
    }
}
