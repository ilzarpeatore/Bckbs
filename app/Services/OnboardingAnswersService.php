<?php

namespace App\Services;

use App\Models\NutritionQuestionnaireAnswer;
use App\Models\ParQAnswer;
use App\Models\TrainingQuestionnaireAnswer;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Reglas de validación y guardado de las respuestas del onboarding v2 (PAR-Q+, cuestionario de
 * entrenamiento y de nutrición). Las usan tanto el cliente sobre sí mismo
 * (API\OnboardingController) como el admin sobre un cliente (Admin\OnboardingController), para
 * que ambos apliquen exactamente las mismas reglas y no se dupliquen.
 */
class OnboardingAnswersService
{
    public static function parqRules(User $user): array
    {
        $isFemale = $user->gender === 'female';

        return [
            'parq_heart_condition'            => 'required|boolean',
            'parq_chest_pain_activity'        => 'required|boolean',
            'parq_chest_pain_rest_last_month' => 'required|boolean',
            'parq_dizziness_balance'          => 'required|boolean',
            'parq_bone_joint_problem'         => 'required|boolean',
            'parq_bp_or_heart_medication'     => 'required|boolean',
            'parq_reason_not_to_exercise'     => 'required|boolean',
            'parq_pregnant_or_possible'                => [$isFemale ? 'required' : 'nullable', 'boolean'],
            'parq_menstrual_change_or_stress_fracture' => [$isFemale ? 'required' : 'nullable', 'boolean'],
            'parq_eating_disorder_history'    => 'required|boolean',
            'parq_fitness_level'              => 'required|integer|min:1|max:10',
            'parq_medical_history'            => 'nullable|string',
            'parq_goals'                      => 'required|string',
        ];
    }

    public static function trainingRules(): array
    {
        return [
            'goal_type'                    => 'required|string|in:lose_fat,gain_muscle,recomposition,maintain',
            'activity_level'               => 'required|string|in:sedentary,light,moderate,active,very_active',
            'lifestyle_type'               => 'required|string|in:mostly_sitting,sometimes_standing,mostly_standing,always_moving,heavy_labor',
            'training_experience_months'   => 'required|integer|min:0',
            'training_days_per_week'       => 'required|integer|min:1|max:7',
            'session_duration_preference'  => 'required|string|in:30,45,60,90,90_plus',
            'training_mindset'             => 'required|string|in:rushed,calm,motivated,unmotivated',
            'previous_coaching'            => 'required|string|in:online_coach,in_person_coach,self_trained',
            'current_routine_style'        => 'required|string|in:improvised,copied,structured,always_same,very_varied',
            'weekly_split_preference'      => 'required|string|in:upper_lower,push_pull,full_body,no_preference',
            'technique_level'              => 'required|integer|min:1|max:10',
            'realistic_goal'               => 'required|string',
        ];
    }

    public static function nutritionRules(): array
    {
        return [
            'allergies_intolerances'      => 'required|string',
            'medications'                 => 'nullable|string',
            'supplements'                 => 'nullable|string',
            'disliked_foods'              => 'nullable|string',
            'liked_foods'                 => 'nullable|string',
            'current_meals_per_day'       => 'required|integer|min:1|max:8',
            'desired_meals_per_day'       => 'required|integer|min:1|max:8',
            'typical_day_meals'           => 'required|string',
            'favorite_meats'              => 'nullable|string',
            'favorite_fish'               => 'nullable|string',
            'favorite_fruits_vegetables'  => 'nullable|string',
            'favorite_combined_dishes'    => 'nullable|string',
            'cooking_minutes_per_meal'    => 'required|integer|min:0|max:180',
            'cooking_skill_level'         => 'required|string|in:beginner,intermediate,advanced',
            'cooks_for_others'            => 'required|boolean',
        ];
    }

    /**
     * Guarda el PAR-Q+ y marca al usuario para revisión si alguna respuesta de riesgo es true.
     * La marca solo se activa aquí; quitarla es una decisión de un coach, no se limpia sola.
     * (Detalle de cada pregunta: ver el docblock histórico en API\OnboardingController::parq.)
     */
    public static function saveParq(User $user, Request $request): ParQAnswer
    {
        $isFemale = $user->gender === 'female';

        $answer = ParQAnswer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'parq_heart_condition'            => $request->parq_heart_condition,
                'parq_chest_pain_activity'        => $request->parq_chest_pain_activity,
                'parq_chest_pain_rest_last_month' => $request->parq_chest_pain_rest_last_month,
                'parq_dizziness_balance'          => $request->parq_dizziness_balance,
                'parq_bone_joint_problem'         => $request->parq_bone_joint_problem,
                'parq_bp_or_heart_medication'     => $request->parq_bp_or_heart_medication,
                'parq_reason_not_to_exercise'     => $request->parq_reason_not_to_exercise,
                // no aplicable a hombre/otro/sin especificar -- se guarda NULL, no false
                // (false significaría "se le preguntó y dijo que no").
                'parq_pregnant_or_possible'                => $isFemale ? $request->boolean('parq_pregnant_or_possible') : null,
                'parq_menstrual_change_or_stress_fracture' => $isFemale ? $request->boolean('parq_menstrual_change_or_stress_fracture') : null,
                'parq_eating_disorder_history'             => $request->parq_eating_disorder_history,
                'parq_fitness_level'              => $request->parq_fitness_level,
                'parq_medical_history'            => $request->parq_medical_history,
                'parq_goals'                      => $request->parq_goals,
            ]
        );

        $riskAnswered = $request->boolean('parq_heart_condition')
            || $request->boolean('parq_chest_pain_activity')
            || $request->boolean('parq_chest_pain_rest_last_month')
            || $request->boolean('parq_dizziness_balance')
            || ($isFemale && $request->boolean('parq_pregnant_or_possible'))
            || ($isFemale && $request->boolean('parq_menstrual_change_or_stress_fracture'))
            || $request->boolean('parq_eating_disorder_history');

        if ($riskAnswered && !$user->flagged_for_review) {
            $user->flagged_for_review = true;
            $user->flagged_for_review_at = now();
            $user->save();
        }

        return $answer;
    }

    public static function saveTraining(User $user, Request $request): TrainingQuestionnaireAnswer
    {
        return TrainingQuestionnaireAnswer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'goal_type'                   => $request->goal_type,
                'activity_level'              => $request->activity_level,
                'lifestyle_type'              => $request->lifestyle_type,
                'training_experience_months'  => $request->training_experience_months,
                'training_days_per_week'      => $request->training_days_per_week,
                'session_duration_preference' => $request->session_duration_preference,
                'training_mindset'            => $request->training_mindset,
                'previous_coaching'           => $request->previous_coaching,
                'current_routine_style'       => $request->current_routine_style,
                'weekly_split_preference'     => $request->weekly_split_preference,
                'technique_level'             => $request->technique_level,
                'realistic_goal'              => $request->realistic_goal,
            ]
        );
    }

    public static function saveNutrition(User $user, Request $request): NutritionQuestionnaireAnswer
    {
        return NutritionQuestionnaireAnswer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'allergies_intolerances'     => $request->allergies_intolerances,
                // Solo si se envían: una versión anterior de la app (que aún no los pregunta)
                // no debe borrar lo que el cliente ya hubiera rellenado.
                ...($request->has('medications') ? ['medications' => $request->medications] : []),
                ...($request->has('supplements') ? ['supplements' => $request->supplements] : []),
                'disliked_foods'             => $request->disliked_foods,
                'liked_foods'                => $request->liked_foods,
                'current_meals_per_day'      => $request->current_meals_per_day,
                'desired_meals_per_day'      => $request->desired_meals_per_day,
                'typical_day_meals'          => $request->typical_day_meals,
                'favorite_meats'             => $request->favorite_meats,
                'favorite_fish'              => $request->favorite_fish,
                'favorite_fruits_vegetables' => $request->favorite_fruits_vegetables,
                'favorite_combined_dishes'   => $request->favorite_combined_dishes,
                'cooking_minutes_per_meal'   => $request->cooking_minutes_per_meal,
                'cooking_skill_level'        => $request->cooking_skill_level,
                'cooks_for_others'           => $request->boolean('cooks_for_others'),
            ]
        );
    }
}
