<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkoutSessionReview;
use App\Models\User;

class WorkoutSessionReviewController extends Controller
{
    /**
     * ACTUALIZADO: admite tanto `workout_day_id` (sistema viejo) como
     * `program_day_assignment_id` (sistema nuevo) — al menos uno de
     * los dos, nunca ambos a la vez.
     */
    public function store(Request $request)
    {
        $request->validate([
            'workout_day_id'            => 'nullable|exists:workout_days,id',
            'program_day_assignment_id' => 'nullable|exists:program_day_assignments,id',
            'difficulty_rating'         => 'nullable|integer|min:1|max:5',
            'comment'                   => 'nullable|string',
        ]);

        if (!$request->workout_day_id && !$request->program_day_assignment_id) {
            return json_message_response('Falta indicar workout_day_id o program_day_assignment_id.', 422);
        }

        $matchCriteria = ['user_id' => auth('sanctum')->id()];
        if ($request->workout_day_id) {
            $matchCriteria['workout_day_id'] = $request->workout_day_id;
        } else {
            $matchCriteria['program_day_assignment_id'] = $request->program_day_assignment_id;
        }

        $review = WorkoutSessionReview::updateOrCreate(
            $matchCriteria,
            [
                'difficulty_rating' => $request->difficulty_rating,
                'comment'           => $request->comment,
                'completed_at'      => now(),
            ]
        );

        return json_custom_response(['data' => $review]);
    }

    public function getList(Request $request)
    {
        $auth_user = auth('sanctum')->user();
        $client_id = $auth_user->id;

        if ($request->has('client_id') && $auth_user->user_type != 'user') {
            $client = User::where('id', $request->client_id)
                ->where('coach_id', $auth_user->id)
                ->first();

            if ($client) {
                $client_id = $client->id;
            }
        }

        $reviews = WorkoutSessionReview::where('user_id', $client_id)
            ->orderByDesc('completed_at')
            ->paginate(config('constant.PER_PAGE_LIMIT', 10));

        $response = [
            'pagination' => json_pagination_response($reviews),
            'data'       => $reviews->items(),
        ];

        return json_custom_response($response);
    }
}
