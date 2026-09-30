<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ParQAnswer;
use App\Models\TrainingQuestionnaireAnswer;
use App\Models\NutritionQuestionnaireAnswer;
use App\Services\OnboardingAnswersService;

/**
 * Onboarding v2 (4 etapas), etapas 2-4 + marcado de completado. La etapa 1
 * (datos personales) reutiliza update-profile y no vive aquí. Ver
 * docs/ONBOARDING_V2.md para el contrato completo request/response.
 */
class OnboardingController extends Controller
{
    /**
     * Devuelve las respuestas de onboarding (par_q/training/nutrition) del
     * usuario autenticado, o null en cada una si esa etapa todavía no se
     * completó. Nuevo (2026-09-18) -- hasta ahora solo existía la versión
     * admin (Admin\OnboardingController::getDetail, requiere user_id y
     * permisos de coach); esta es la versión "mis propios datos" que
     * consume la pantalla de edición de onboarding en la app (repo bsa,
     * pantalla de Cuenta). Misma forma de respuesta que la versión admin
     * para las 3 claves, para no mantener 2 contratos distintos.
     */
    public function myAnswers(Request $request)
    {
        $user = auth('sanctum')->user();

        return json_custom_response([
            'data' => [
                'par_q'                   => ParQAnswer::where('user_id', $user->id)->first(),
                'training_questionnaire'  => TrainingQuestionnaireAnswer::where('user_id', $user->id)->first(),
                'nutrition_questionnaire' => NutritionQuestionnaireAnswer::where('user_id', $user->id)->first(),
            ],
        ]);
    }

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

        // Reglas y guardado compartidos con el admin (ver OnboardingAnswersService).
        $request->validate(OnboardingAnswersService::parqRules($user));
        OnboardingAnswersService::saveParq($user, $request);

        return json_custom_response(['message' => 'OK', 'status' => true]);
    }

    /**
     * Etapa 3 — cuestionario de entrenamiento.
     */
    public function trainingQuestionnaire(Request $request)
    {
        $request->validate(OnboardingAnswersService::trainingRules());

        OnboardingAnswersService::saveTraining(auth('sanctum')->user(), $request);

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
        $request->validate(OnboardingAnswersService::nutritionRules());

        OnboardingAnswersService::saveNutrition(auth('sanctum')->user(), $request);

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
     *
     * Fix 2026-09-18 (bug real, MUY grave: dos altas nuevas -- Osas
     * Ehigiator user_id=103, Alberto Martín user_id=104 -- quedaron con
     * onboarding_completed_at puesto pese a que par_q_answers y
     * nutrition_questionnaire_answers nunca llegaron a crearse, por un
     * fallo de red puntual en el cliente durante el registro diferido que
     * este endpoint no detectaba). Antes de aquí, este método marcaba
     * completado sin comprobar nada -- ahora exige que las 3 tablas de
     * onboarding existan de verdad. Si falta alguna, devuelve 422 con la
     * lista de qué falta, para que el cliente (ver
     * AuthContext.completeOnboarding() en el repo bsa) NO marque el
     * onboarding como completo localmente y el usuario vuelva a esa etapa
     * la próxima vez que abra la app.
     */
    public function complete(Request $request)
    {
        $user = auth('sanctum')->user();

        $missing = [];
        if (!ParQAnswer::where('user_id', $user->id)->exists()) {
            $missing[] = 'par_q';
        }
        if (!TrainingQuestionnaireAnswer::where('user_id', $user->id)->exists()) {
            $missing[] = 'training_questionnaire';
        }
        if (!NutritionQuestionnaireAnswer::where('user_id', $user->id)->exists()) {
            $missing[] = 'nutrition_questionnaire';
        }

        if (!empty($missing)) {
            return json_custom_response([
                'message' => 'Faltan etapas del onboarding por completar.',
                'missing_stages' => $missing,
            ], 422);
        }

        if ($user->onboarding_completed_at === null) {
            $user->onboarding_completed_at = now();
            $user->save();
        }

        // Packs comprados en la web: empiezan ahora que el coach ya tiene
        // sus datos (docs/PACKS_WEB.md).
        \App\Services\PackPurchaseService::startPendingFor($user);

        return json_custom_response(['message' => 'OK', 'status' => true]);
    }
}
