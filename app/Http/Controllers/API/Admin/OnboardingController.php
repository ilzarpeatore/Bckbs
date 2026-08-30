<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ParQAnswer;
use App\Models\TrainingQuestionnaireAnswer;
use App\Models\NutritionQuestionnaireAnswer;
use Illuminate\Http\Request;

/**
 * Visibilidad admin del onboarding v2 (estado de completado, flag PAR-Q de
 * revisión, y las 3 respuestas de cuestionario por cliente). JSON puro,
 * consumido por el panel admin (repo aparte) -- ver docs/ONBOARDING_V2.md.
 */
class OnboardingController extends Controller
{
    public function getList(Request $request)
    {
        $query = User::role('user');

        if ($request->filled('flagged_for_review')) {
            $query->where('flagged_for_review', $request->boolean('flagged_for_review'));
        }

        if ($request->filled('completed')) {
            if ($request->boolean('completed')) {
                $query->whereNotNull('onboarding_completed_at');
            } else {
                $query->whereNull('onboarding_completed_at');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('username', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 50);
        $users = $query->select([
                'id', 'first_name', 'last_name', 'display_name', 'email', 'username',
                'coach_id', 'flagged_for_review', 'flagged_for_review_at', 'onboarding_completed_at',
            ])
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = collect($users->items())->map(function ($user) {
            return [
                'id'                       => $user->id,
                'first_name'               => $user->first_name,
                'last_name'                => $user->last_name,
                'display_name'             => $user->display_name,
                'email'                    => $user->email,
                'username'                 => $user->username,
                'coach_id'                 => $user->coach_id,
                'flagged_for_review'       => (bool) $user->flagged_for_review,
                'flagged_for_review_at'    => $user->flagged_for_review_at,
                'onboarding_completed'     => $user->onboarding_completed_at !== null,
                'onboarding_completed_at'  => $user->onboarding_completed_at,
            ];
        });

        $response = [
            'pagination' => json_pagination_response($users),
            'data'       => $data,
        ];

        return json_custom_response($response);
    }

    public function getDetail(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $user = User::with('userProfile')->find($request->user_id);

        if (!$user) {
            return json_message_response('User not found.', 404);
        }

        $response = [
            'data' => [
                'id'                      => $user->id,
                'first_name'              => $user->first_name,
                'last_name'               => $user->last_name,
                'display_name'            => $user->display_name,
                'email'                   => $user->email,
                'username'                => $user->username,
                'gender'                  => $user->gender,
                'coach_id'                => $user->coach_id,
                'flagged_for_review'      => (bool) $user->flagged_for_review,
                'flagged_for_review_at'   => $user->flagged_for_review_at,
                'onboarding_completed'    => $user->onboarding_completed_at !== null,
                'onboarding_completed_at' => $user->onboarding_completed_at,
                'personal_data'           => $user->userProfile,
                'par_q'                   => ParQAnswer::where('user_id', $user->id)->first(),
                'training_questionnaire'  => TrainingQuestionnaireAnswer::where('user_id', $user->id)->first(),
                'nutrition_questionnaire' => NutritionQuestionnaireAnswer::where('user_id', $user->id)->first(),
            ],
        ];

        return json_custom_response($response);
    }
}
