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
    /**
     * Preguntas nuevas del onboarding (2026-09-29) que solo tienen sentido si
     * una pregunta "puerta" anterior se respondió de cierta forma. Si la
     * puerta llega cerrada, sus dependientes se guardan a NULL aunque vengan
     * con valor (respuestas viejas que la app no limpió al cambiar de
     * opinión el usuario). Formato: puerta => [valor que la abre, dependientes].
     */
    private const PARQ_DEPENDENTS = [
        'injury_has' => [true, [
            'injury_zone', 'injury_painful_movement', 'injury_phase',
            'injury_worsens_with_impact', 'injury_professional_clearance', 'injury_other_notes',
        ]],
    ];

    private const TRAINING_DEPENDENTS = [
        'practices_other_sport' => [true, ['other_sport_description']],
        'has_target_event' => [true, ['target_event_description', 'target_event_date']],
    ];

    /**
     * Campos nuevos (2026-09-29): todos opcionales en el backend, y solo se
     * escriben si vienen en la petición -- una versión anterior de la app,
     * que todavía no los pregunta, no debe borrar lo que el cliente ya
     * hubiera rellenado (mismo criterio que medications/supplements).
     */
    private static function presentFields(Request $request, array $fields, array $dependents = []): array
    {
        $data = [];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }
        foreach ($dependents as $gate => [$openValue, $deps]) {
            $gateValue = array_key_exists($gate, $data)
                ? filter_var($data[$gate], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null;
            if ($gateValue !== null && $gateValue !== $openValue) {
                foreach ($deps as $dep) {
                    $data[$dep] = null;
                }
            }
        }

        return $data;
    }

    private const PARQ_EXTENDED_FIELDS = [
        'injury_has', 'injury_zone', 'injury_painful_movement', 'injury_phase',
        'injury_worsens_with_impact', 'injury_professional_clearance', 'injury_other_notes',
    ];

    private const TRAINING_EXTENDED_FIELDS = [
        'practices_other_sport', 'other_sport_description', 'has_target_event', 'target_event_description',
        'target_event_date', 'work_schedule', 'training_time_of_day', 'sleep_hours', 'sleep_regularity',
        'stress_level', 'training_location', 'home_equipment', 'equipment_notes',
        'strength_squat_kg', 'strength_squat_reps', 'strength_deadlift_kg', 'strength_deadlift_reps',
        'strength_db_bench_kg', 'strength_db_bench_reps', 'strength_db_row_kg', 'strength_db_row_reps',
    ];

    private const NUTRITION_EXTENDED_FIELDS = [
        'weekly_food_budget', 'meals_away_from_home', 'meal_schedule', 'intermittent_fasting',
        'alcohol_frequency', 'water_intake', 'previous_diets',
    ];

    public const HOME_EQUIPMENT_OPTIONS = [
        'dumbbells', 'barbell_plates', 'rack', 'bench', 'pullup_bar', 'kettlebells', 'bands', 'suspension', 'cardio_machine', 'cables', 'none',
    ];

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
            // Lesión/molestia principal (2026-09-29) -- ver la migración add_extended_onboarding_fields.
            'injury_has'                      => 'nullable|boolean',
            'injury_zone'                     => 'nullable|string|in:neck,shoulder,elbow,wrist_hand,upper_back,lower_back,hip,knee,ankle_foot,other',
            'injury_painful_movement'         => 'nullable|string|max:2000',
            'injury_phase'                    => 'nullable|string|in:acute,recovering,chronic_controlled',
            'injury_worsens_with_impact'      => 'nullable|string|in:yes,no,unknown',
            'injury_professional_clearance'   => 'nullable|string|in:cleared,with_limits,not_consulted',
            'injury_other_notes'              => 'nullable|string|max:2000',
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
            // Solo si ya ha entrenado (2026-09-29): a quien empieza de cero no se le pregunta.
            'training_mindset'             => 'required_unless:training_experience_months,0|nullable|string|in:rushed,calm,motivated,unmotivated',
            'previous_coaching'            => 'required_unless:training_experience_months,0|nullable|string|in:online_coach,in_person_coach,self_trained',
            'current_routine_style'        => 'required_unless:training_experience_months,0|nullable|string|in:improvised,copied,structured,always_same,very_varied',
            'weekly_split_preference'      => 'required_unless:training_experience_months,0|nullable|string|in:upper_lower,push_pull,full_body,no_preference',
            'technique_level'              => 'required_unless:training_experience_months,0|nullable|integer|min:1|max:10',
            'realistic_goal'               => 'required_unless:training_experience_months,0|nullable|string',
            // Contexto ampliado (2026-09-29): deporte/evento, vida, material y referencias de fuerza.
            'practices_other_sport'        => 'nullable|boolean',
            'other_sport_description'      => 'nullable|string|max:2000',
            'has_target_event'             => 'nullable|boolean',
            'target_event_description'     => 'nullable|string|max:255',
            'target_event_date'            => 'nullable|date',
            'work_schedule'                => 'nullable|string|in:morning,afternoon,split,rotating_shifts,night,flexible,not_working',
            'training_time_of_day'         => 'nullable|string|in:morning,midday,afternoon,evening,variable',
            'sleep_hours'                  => 'nullable|integer|min:3|max:12',
            'sleep_regularity'             => 'nullable|string|in:regular,irregular',
            'stress_level'                 => 'nullable|integer|min:1|max:10',
            // Lugar + material en una sola respuesta (2026-09-29); basic_gym/home/outdoor/mixed
            // son los valores de la primera versión, se siguen aceptando.
            'training_location'            => 'nullable|string|in:full_gym,gym_basic,gym_no_equipment,home_full,home_basic,home_none,basic_gym,home,outdoor,mixed',
            'home_equipment'               => 'nullable|array',
            'home_equipment.*'             => 'string|in:' . implode(',', self::HOME_EQUIPMENT_OPTIONS),
            'equipment_notes'              => 'nullable|string|max:2000',
            'strength_squat_kg'            => 'nullable|numeric|min:0|max:500',
            'strength_squat_reps'          => 'nullable|integer|min:1|max:50',
            'strength_deadlift_kg'         => 'nullable|numeric|min:0|max:500',
            'strength_deadlift_reps'       => 'nullable|integer|min:1|max:50',
            'strength_db_bench_kg'         => 'nullable|numeric|min:0|max:200',
            'strength_db_bench_reps'       => 'nullable|integer|min:1|max:50',
            'strength_db_row_kg'           => 'nullable|numeric|min:0|max:200',
            'strength_db_row_reps'         => 'nullable|integer|min:1|max:50',
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
            // Nutrición práctica (2026-09-29).
            'weekly_food_budget'          => 'nullable|string|in:under_40,40_70,70_100,100_150,over_150,unknown',
            'meals_away_from_home'        => 'nullable|string|in:home,tupper,restaurant,mixed',
            'meal_schedule'               => 'nullable|string|max:2000',
            'intermittent_fasting'        => 'nullable|boolean',
            'alcohol_frequency'           => 'nullable|string|in:never,occasional,weekends,several_per_week,daily',
            'water_intake'                => 'nullable|string|in:under_1l,1_1_5l,1_5_2l,2_3l,over_3l',
            'previous_diets'              => 'nullable|string|max:2000',
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
                ...self::presentFields($request, self::PARQ_EXTENDED_FIELDS, self::PARQ_DEPENDENTS),
            ]
        );

        $riskAnswered = $request->boolean('parq_heart_condition')
            || $request->boolean('parq_chest_pain_activity')
            || $request->boolean('parq_chest_pain_rest_last_month')
            || $request->boolean('parq_dizziness_balance')
            || ($isFemale && $request->boolean('parq_pregnant_or_possible'))
            || ($isFemale && $request->boolean('parq_menstrual_change_or_stress_fracture'))
            || $request->boolean('parq_eating_disorder_history')
            // Lesión en fase aguda (2026-09-29): el agente de entrenamiento excluye el patrón
            // por completo hasta que el coach lo revise -- mismo tratamiento de "revisar antes".
            || ($request->boolean('injury_has') && $request->input('injury_phase') === 'acute');

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
                ...self::presentFields($request, self::TRAINING_EXTENDED_FIELDS, self::TRAINING_DEPENDENTS),
                // Gimnasio completo: no se pregunta el material; se limpia el de una respuesta anterior.
                ...($request->input('training_location') === 'full_gym' ? ['home_equipment' => null, 'equipment_notes' => null] : []),
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
                ...self::presentFields($request, self::NUTRITION_EXTENDED_FIELDS),
            ]
        );
    }
}
