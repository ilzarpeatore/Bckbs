<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ParQAnswer;
use App\Models\TrainingQuestionnaireAnswer;
use App\Models\NutritionQuestionnaireAnswer;

/**
 * Onboarding v2 (4 etapas), etapas 2-4 + marcado de completado. La etapa 1
 * (datos personales) reutiliza update-profile y no vive aquí. Ver
 * docs/ONBOARDING_V2.md para el contrato completo request/response.
 */
class OnboardingController extends Controller
{
    /**
     * Etapa 2 — PAR-Q+. Si alguna respuesta de riesgo cardíaco/mareos es
     * true, marca al usuario para revisión de un coach antes de asignarle
     * un plan (decisión de producto confirmada).
     */
    public function parq(Request $request)
    {
        $request->validate([
            'parq_heart_condition'            => 'required|boolean',
            'parq_chest_pain_activity'        => 'required|boolean',
            'parq_chest_pain_rest_last_month' => 'required|boolean',
            'parq_dizziness_balance'          => 'required|boolean',
            'parq_bone_joint_problem'         => 'required|boolean',
            'parq_bp_or_heart_medication'     => 'required|boolean',
            'parq_reason_not_to_exercise'     => 'required|boolean',
            'parq_fitness_level'              => 'required|integer|min:1|max:10',
            'parq_medical_history'            => 'nullable|string',
            'parq_goals'                      => 'required|string',
        ]);

        $user = auth('sanctum')->user();

        ParQAnswer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'parq_heart_condition'            => $request->parq_heart_condition,
                'parq_chest_pain_activity'        => $request->parq_chest_pain_activity,
                'parq_chest_pain_rest_last_month' => $request->parq_chest_pain_rest_last_month,
                'parq_dizziness_balance'          => $request->parq_dizziness_balance,
                'parq_bone_joint_problem'         => $request->parq_bone_joint_problem,
                'parq_bp_or_heart_medication'     => $request->parq_bp_or_heart_medication,
                'parq_reason_not_to_exercise'     => $request->parq_reason_not_to_exercise,
                'parq_fitness_level'              => $request->parq_fitness_level,
                'parq_medical_history'            => $request->parq_medical_history,
                'parq_goals'                      => $request->parq_goals,
            ]
        );

        $riskAnswered = $request->boolean('parq_heart_condition')
            || $request->boolean('parq_chest_pain_activity')
            || $request->boolean('parq_chest_pain_rest_last_month')
            || $request->boolean('parq_dizziness_balance');

        if ($riskAnswered && !$user->flagged_for_review) {
            $user->flagged_for_review = true;
            $user->flagged_for_review_at = now();
            $user->save();
        }

        return json_custom_response(['message' => 'OK', 'status' => true]);
    }

    /**
     * Etapa 3 — cuestionario de entrenamiento.
     */
    public function trainingQuestionnaire(Request $request)
    {
        $request->validate([
            'goal_type'                    => 'required|string|in:lose_fat,gain_muscle,recomposition,maintain',
            'activity_level'                => 'required|string|in:sedentary,light,moderate,active,very_active',
            'lifestyle_type'                => 'required|string|in:mostly_sitting,sometimes_standing,mostly_standing,always_moving,heavy_labor',
            'training_experience_months'    => 'required|integer|min:0',
            'training_days_per_week'        => 'required|integer|min:1|max:7',
            'session_duration_preference'   => 'required|string|in:30,45,60,90,90_plus',
            'training_mindset'              => 'required|string|in:rushed,calm,motivated,unmotivated',
            'previous_coaching'             => 'required|string|in:online_coach,in_person_coach,self_trained',
            'current_routine_style'         => 'required|string|in:improvised,copied,structured,always_same,very_varied',
            'weekly_split_preference'       => 'required|string|in:upper_lower,push_pull,full_body,no_preference',
            'technique_level'               => 'required|integer|min:1|max:10',
            'realistic_goal'                => 'required|string',
        ]);

        $user = auth('sanctum')->user();

        TrainingQuestionnaireAnswer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'goal_type'                   => $request->goal_type,
                'activity_level'               => $request->activity_level,
                'lifestyle_type'               => $request->lifestyle_type,
                'training_experience_months'   => $request->training_experience_months,
                'training_days_per_week'       => $request->training_days_per_week,
                'session_duration_preference'  => $request->session_duration_preference,
                'training_mindset'             => $request->training_mindset,
                'previous_coaching'            => $request->previous_coaching,
                'current_routine_style'        => $request->current_routine_style,
                'weekly_split_preference'      => $request->weekly_split_preference,
                'technique_level'              => $request->technique_level,
                'realistic_goal'               => $request->realistic_goal,
            ]
        );

        return json_custom_response(['message' => 'OK', 'status' => true]);
    }

    /**
     * Etapa 4 — cuestionario de nutrición.
     */
    public function nutritionQuestionnaire(Request $request)
    {
        $request->validate([
            'allergies_intolerances'      => 'required|string',
            'disliked_foods'              => 'nullable|string',
            'liked_foods'                 => 'nullable|string',
            'current_meals_per_day'       => 'required|integer|min:1|max:8',
            'desired_meals_per_day'       => 'required|integer|min:1|max:8',
            'typical_day_meals'           => 'required|string',
            'favorite_meats'              => 'nullable|string',
            'favorite_fish'               => 'nullable|string',
            'favorite_fruits_vegetables'  => 'nullable|string',
            'favorite_combined_dishes'    => 'nullable|string',
        ]);

        $user = auth('sanctum')->user();

        NutritionQuestionnaireAnswer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'allergies_intolerances'     => $request->allergies_intolerances,
                'disliked_foods'             => $request->disliked_foods,
                'liked_foods'                => $request->liked_foods,
                'current_meals_per_day'      => $request->current_meals_per_day,
                'desired_meals_per_day'      => $request->desired_meals_per_day,
                'typical_day_meals'          => $request->typical_day_meals,
                'favorite_meats'             => $request->favorite_meats,
                'favorite_fish'              => $request->favorite_fish,
                'favorite_fruits_vegetables' => $request->favorite_fruits_vegetables,
                'favorite_combined_dishes'   => $request->favorite_combined_dishes,
            ]
        );

        return json_custom_response(['message' => 'OK', 'status' => true]);
    }

    /**
     * Marca el onboarding como completado para el usuario autenticado.
     * Idempotente — llamarlo dos veces no es un error.
     */
    public function complete(Request $request)
    {
        $user = auth('sanctum')->user();

        if ($user->onboarding_completed_at === null) {
            $user->onboarding_completed_at = now();
            $user->save();
        }

        return json_custom_response(['message' => 'OK', 'status' => true]);
    }
}
