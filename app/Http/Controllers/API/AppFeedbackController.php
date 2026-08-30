<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppFeedback;
use Illuminate\Http\Request;

class AppFeedbackController extends Controller
{
    /**
     * POST v1/app-feedback (item 5 del backlog). `user_id` sale del token,
     * no del payload -- ver contrato en docs del backlog.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'            => 'required|in:feature_request,bug_report',
            'title'           => 'required|string|max:100',
            'description'     => 'required|string',
            'section'         => 'required|in:workout,nutrition,habits,metrics,other',
            'section_other'   => 'required_if:section,other|nullable|string|max:255',
            'diagnostics_log' => 'nullable|string',
            'app_version'     => 'nullable|string|max:50',
            'platform'        => 'required|in:ios,android',
        ]);

        $validated['user_id'] = auth('sanctum')->id();

        $feedback = AppFeedback::create($validated);

        return json_custom_response(['data' => $feedback], 201);
    }
}
