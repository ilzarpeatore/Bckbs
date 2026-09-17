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
     *
     * parq_pregnant_or_possible / parq_menstrual_change_or_stress_fracture /
     * parq_eating_disorder_history (2026-09-16): el diseño del Asistente de
     * Programación de Entrenamiento (repo AgenticdesignBS,
     * contraindicaciones-medicas.md) asumía estas tres preguntas desde el
     * principio, pero nunca se recogieron aquí -- sin dato real que leer,
     * ese cribado no podía activarse nunca en producción. Se tratan igual
     * que el resto de banderas de riesgo: cualquiera en true marca
     * flagged_for_review.
     *
     * parq_pregnant_or_possible / parq_menstrual_change_or_stress_fracture
     * (2026-09-16, decisión de producto): solo tienen sentido para un
     * perfil de mujer (`users.gender`, ya recogido en la etapa 1 del
     * onboarding -- update-profile -- antes de llegar aquí). Para
     * hombre/otro/sin especificar no se piden (nullable) ni se muestran en
     * la app -- esa parte de mostrar/ocultar el campo vive en el
     * frontend/app, no en este backend. parq_eating_disorder_history SÍ
     * aplica a cualquier género, se mantiene siempre obligatoria.
     */
    public function parq(Request $request)
    {
        $user = auth('sanctum')->user();
        $isFemale = $user->gender === 'female';

        $request->validate([
            'parq_heart_condition'            => 'required|boolean',
            'parq_chest_pain_activity'        => 'required|boolean',
            'parq_chest_pain_rest_last_month' => 'required|boolean',
            'parq_dizziness_balance'          => 'required|boolean',
            'parq_bone_joint_problem'         => 'required|boolean',
            'parq_bp_or_heart_medication'     => 'required|boolean',
            'parq_reason_not_to_exercise'     => 'required|boolean',
            'parq_pregnant_or_possible'                 => [$isFemale ? 'required' : 'nullable', 'boolean'],
            'parq_menstrual_change_or_stress_fracture'  => [$isFemale ? 'required' : 'nullable', 'boolean'],
            'parq_eating_disorder_history'              => 'required|boolean',
            'parq_fitness_level'              => 'required|integer|min:1|max:10',
            'parq_medical_history'            => 'nullable|string',
            'parq_goals'                      => 'required|string',
        ]);

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
     *
     * cooking_minutes_per_meal/cooking_skill_level/cooks_for_others
     * (2026-09-16): `disponibilidad_cocina` es requerido por
     * perfil-nutricional.schema.json (repo AgenticdesignBS) desde el primer
     * borrador del Asistente de Programación de Nutrición, pero nunca se
     * preguntó aquí -- sin este dato el Productor no puede saber si puede
     * proponer una receta de 45 minutos o solo de 10.
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
            'cooking_minutes_per_meal'    => 'required|integer|min:0|max:180',
            'cooking_skill_level'         => 'required|string|in:beginner,intermediate,advanced',
            'cooks_for_others'            => 'required|boolean',
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
                'cooking_minutes_per_meal'   => $request->cooking_minutes_per_meal,
                'cooking_skill_level'        => $request->cooking_skill_level,
                'cooks_for_others'           => $request->boolean('cooks_for_others'),
            ]
        );

        return json_custom_response(['message' => 'OK', 'status' => true]);
    }

    /**
     * Actualiza SOLO la disponibilidad de entrenamiento (días/semana y
     * duración de sesión preferida) sin reenviar el resto del cuestionario
     * de la etapa 3 -- ese endpoint (trainingQuestionnaire) exige todos los
     * campos como `required`, lo que lo hace inviable para "el cliente
     * cambió de horario" después del onboarding. Requiere que el cliente ya
     * haya completado la etapa 3 (mismo criterio de guarda que
     * Admin\OnboardingController::updateTrainingExperience).
     */
    public function updateTrainingAvailability(Request $request)
    {
        $request->validate([
            'training_days_per_week'      => 'required|integer|min:1|max:7',
            'session_duration_preference' => 'required|string|in:30,45,60,90,90_plus',
        ]);

        $user = auth('sanctum')->user();
        $answer = TrainingQuestionnaireAnswer::where('user_id', $user->id)->first();

        if (!$answer) {
            return json_message_response('Todavía no has completado el cuestionario de entrenamiento del onboarding.', 422);
        }

        $answer->training_days_per_week = $request->integer('training_days_per_week');
        $answer->session_duration_preference = $request->session_duration_preference;
        $answer->save();

        return json_custom_response(['data' => $answer]);
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
