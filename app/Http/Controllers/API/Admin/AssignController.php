<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AssignDiet;
use App\Models\AssignWorkout;
use App\Models\User;
use App\Models\Diet;
use App\Models\Workout;

class AssignController extends Controller
{
    public function assignDietList(Request $request)
    {
        $query = AssignDiet::with(['user', 'diet']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        $items = $query->orderBy('id', 'desc')->paginate($perPage);

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ]);
    }

    public function assignDietStore(Request $request)
    {
        $request->validate([
            'user_id'  => 'required|exists:users,id',
            'diet_id'  => 'required|exists:diets,id',
        ]);

        $exists = AssignDiet::where('user_id', $request->user_id)
                            ->where('diet_id', $request->diet_id)
                            ->exists();

        if ($exists) {
            return json_message_response('Diet already assigned to this user.', 400);
        }

        $assign = AssignDiet::create([
            'user_id' => $request->user_id,
            'diet_id' => $request->diet_id,
        ]);

        return json_custom_response([
            'message' => 'Diet assigned successfully.',
            'data'    => $assign,
        ], 201);
    }

    public function assignDietDestroy($id)
    {
        $assign = AssignDiet::find($id);

        if (!$assign) {
            return json_message_response('Assignment not found.', 404);
        }

        $assign->delete();

        return json_message_response('Diet assignment removed.');
    }

    public function assignWorkoutList(Request $request)
    {
        $query = AssignWorkout::with(['user', 'workout']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        $items = $query->orderBy('id', 'desc')->paginate($perPage);

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ]);
    }

    public function assignWorkoutStore(Request $request)
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'workout_id' => 'required|exists:workouts,id',
        ]);

        $exists = AssignWorkout::where('user_id', $request->user_id)
                               ->where('workout_id', $request->workout_id)
                               ->exists();

        if ($exists) {
            return json_message_response('Workout already assigned to this user.', 400);
        }

        $assign = AssignWorkout::create([
            'user_id'    => $request->user_id,
            'workout_id' => $request->workout_id,
        ]);

        return json_custom_response([
            'message' => 'Workout assigned successfully.',
            'data'    => $assign,
        ], 201);
    }

    public function assignWorkoutDestroy($id)
    {
        $assign = AssignWorkout::find($id);

        if (!$assign) {
            return json_message_response('Assignment not found.', 404);
        }

        $assign->delete();

        return json_message_response('Workout assignment removed.');
    }
}
