<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ParQAnswer;
use App\Models\TrainingQuestionnaireAnswer;
use App\Models\NutritionQuestionnaireAnswer;
use Illuminate\Http\Request;
use App\Services\AuditLogger;
use App\Services\OnboardingAnswersService;
use App\Support\FuzzySearch;

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
            FuzzySearch::apply($query, ['first_name', 'last_name', 'email', 'username'], $request->search);
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

    /**
     * Edición admin de las respuestas del onboarding de un cliente (2026-09-24, ítem 25 del
     * roadmap). Usa las mismas reglas y el mismo guardado que la app del cliente
     * (OnboardingAnswersService), así que el cliente ve el cambio en "Mis respuestas del
     * onboarding" al momento. Cada cambio queda en audit_logs con el admin y los CAMPOS
     * modificados (no los valores: son datos de salud).
     */
    public function updateParQ(Request $request)
    {
        $user = $this->targetClient($request);
        $request->validate(OnboardingAnswersService::parqRules($user));

        return $this->saveAudited($user, 'par_q', ParQAnswer::where('user_id', $user->id)->first(), fn () => OnboardingAnswersService::saveParq($user, $request));
    }

    public function updateTrainingQuestionnaire(Request $request)
    {
        $user = $this->targetClient($request);
        $request->validate(OnboardingAnswersService::trainingRules());

        return $this->saveAudited($user, 'training_questionnaire', TrainingQuestionnaireAnswer::where('user_id', $user->id)->first(), fn () => OnboardingAnswersService::saveTraining($user, $request));
    }

    public function updateNutritionQuestionnaire(Request $request)
    {
        $user = $this->targetClient($request);
        $request->validate(OnboardingAnswersService::nutritionRules());

        return $this->saveAudited($user, 'nutrition_questionnaire', NutritionQuestionnaireAnswer::where('user_id', $user->id)->first(), fn () => OnboardingAnswersService::saveNutrition($user, $request));
    }

    private function targetClient(Request $request): User
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        return User::findOrFail($request->user_id);
    }

    private function saveAudited(User $user, string $section, $before, \Closure $save)
    {
        $beforeAttrs = $before ? $before->getAttributes() : [];
        $answer = $save();
        $changed = [];
        foreach ($answer->getAttributes() as $k => $v) {
            if (in_array($k, ['id', 'user_id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            $norm = fn ($x) => is_bool($x) ? (string) (int) $x : (string) ($x ?? '');
            if (!$before || $norm($beforeAttrs[$k] ?? null) !== $norm($v)) {
                $changed[] = $k;
            }
        }

        AuditLogger::log(
            $before ? 'update' : 'create',
            'onboarding_' . $section,
            $user->id,
            'Respuestas del onboarding editadas por un admin. Campos: ' . ($changed ? implode(', ', $changed) : 'sin cambios'),
        );

        $user->refresh();

        return json_custom_response([
            'data' => [
                $section => $answer,
                'flagged_for_review' => (bool) $user->flagged_for_review,
                'flagged_for_review_at' => $user->flagged_for_review_at,
            ],
        ]);
    }

    /**
     * Plan de Optimización del Motor de Auto-Regulación, Ronda 7 ítems
     * 23-24 (docs/Motor_Autorregulacion_Analisis.md): el nivel de
     * experiencia autoevaluado por el cliente en el onboarding
     * (training_experience_months/technique_level) no se podía corregir
     * desde ningún sitio -- un coach con criterio profesional (evaluación
     * en persona, meses de seguimiento real) no tenía forma de ajustarlo.
     *
     * No pisa el valor autoevaluado: escribe en las columnas
     * `_coach` (override), separadas y con trazabilidad
     * (overridden_by_id/overridden_at) -- ver
     * TrainingQuestionnaireAnswer::effectiveExperienceMonths(), que el
     * motor de reglas ya usa (ConditionVariable::NIVEL_EXPERIENCIA).
     *
     * Requiere que el cliente ya tenga una fila (haya completado la etapa 3
     * del onboarding) -- el resto de columnas de esta tabla son NOT NULL
     * sin default (goal_type, activity_level, realistic_goal...), así que
     * crear una fila nueva solo con el override dejaría un registro
     * inválido. Si el cliente aún no completó el cuestionario, no hay
     * autoevaluación que corregir todavía -- 422 explícito.
     */
    public function updateTrainingExperience(Request $request)
    {
        $request->validate([
            'user_id'                     => 'required|exists:users,id',
            'training_experience_months'  => 'nullable|integer|min:0',
            'technique_level'              => 'nullable|integer|min:1|max:10',
        ]);

        if (!$request->filled('training_experience_months') && !$request->filled('technique_level')) {
            return json_message_response('Debes indicar al menos training_experience_months o technique_level.', 422);
        }

        $answer = TrainingQuestionnaireAnswer::where('user_id', $request->user_id)->first();
        if (!$answer) {
            return json_message_response('Este cliente todavía no completó el cuestionario de entrenamiento del onboarding.', 422);
        }

        if ($request->filled('training_experience_months')) {
            $answer->training_experience_months_coach = $request->integer('training_experience_months');
        }
        if ($request->filled('technique_level')) {
            $answer->technique_level_coach = $request->integer('technique_level');
        }
        $answer->overridden_by_id = auth('sanctum')->id();
        $answer->overridden_at = now();
        $answer->save();

        return json_custom_response(['data' => $answer]);
    }
}
