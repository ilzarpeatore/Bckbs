<?php

namespace Tests\Feature;

use App\Models\NutritionQuestionnaireAnswer;
use App\Models\ParQAnswer;
use App\Models\Role;
use App\Models\TrainingQuestionnaireAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cubre tres huecos reales encontrados al comparar el onboarding (Bckbs)
 * contra los esquemas de AgenticdesignBS:
 *
 * 1. par_q_answers no preguntaba embarazo/posibilidad, alteración
 *    menstrual/fractura por estrés (RED-S) ni trastorno alimentario --
 *    contraindicaciones-medicas.md (agente de entrenamiento) asumía estos
 *    tres datos desde el diseño, pero nunca se recogieron.
 * 2. nutrition_questionnaire_answers no preguntaba disponibilidad de
 *    cocina -- perfil-nutricional.schema.json lo requiere desde el primer
 *    borrador del agente de nutrición.
 * 3. No existía forma de que el cliente actualizara su disponibilidad de
 *    entrenamiento sin repetir todo el cuestionario de la etapa 3.
 */
class OnboardingSafetyAndPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
    }

    private function makeUser(?string $gender = null): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'user_' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'user_type' => 'user',
            'status' => 'active',
            'login_type' => 'manual',
            'gender' => $gender,
        ]);
        $user->assignRole('user');

        return $user;
    }

    private function baseParqPayload(array $overrides = []): array
    {
        return array_merge([
            'parq_heart_condition' => false,
            'parq_chest_pain_activity' => false,
            'parq_chest_pain_rest_last_month' => false,
            'parq_dizziness_balance' => false,
            'parq_bone_joint_problem' => false,
            'parq_bp_or_heart_medication' => false,
            'parq_reason_not_to_exercise' => false,
            'parq_pregnant_or_possible' => false,
            'parq_menstrual_change_or_stress_fracture' => false,
            'parq_eating_disorder_history' => false,
            'parq_fitness_level' => 5,
            'parq_goals' => 'Ganar fuerza',
        ], $overrides);
    }

    // ═══ PAR-Q+ -- nuevas preguntas de seguridad ═══════════════════════

    public function test_parq_requires_pregnancy_and_menstrual_fields_for_female_profile(): void
    {
        $user = $this->makeUser('female');
        Sanctum::actingAs($user, ['*']);

        $payload = $this->baseParqPayload();
        unset($payload['parq_pregnant_or_possible']);

        $response = $this->postJson('/api/v1/onboarding/par-q', $payload);

        $response->assertStatus(422);
        $this->assertArrayHasKey('parq_pregnant_or_possible', $response->json('errors', []));
    }

    public function test_parq_does_not_require_pregnancy_and_menstrual_fields_for_male_profile(): void
    {
        $user = $this->makeUser('male');
        Sanctum::actingAs($user, ['*']);

        $payload = $this->baseParqPayload();
        unset($payload['parq_pregnant_or_possible'], $payload['parq_menstrual_change_or_stress_fracture']);

        $response = $this->postJson('/api/v1/onboarding/par-q', $payload);

        $response->assertStatus(200);
        $answer = ParQAnswer::where('user_id', $user->id)->first();
        $this->assertNull($answer->parq_pregnant_or_possible);
        $this->assertNull($answer->parq_menstrual_change_or_stress_fracture);
    }

    public function test_parq_stores_null_not_false_for_pregnancy_fields_on_male_profile_even_if_sent(): void
    {
        // Defensa en profundidad: si el frontend igualmente enviara estos campos
        // para un hombre (bug de UI), el backend no debe guardar 'false' -- false
        // significa "se le preguntó y dijo que no", null significa "no aplica".
        $user = $this->makeUser('male');
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/par-q', $this->baseParqPayload([
            'parq_pregnant_or_possible' => true,
            'parq_menstrual_change_or_stress_fracture' => true,
        ]))->assertStatus(200);

        $answer = ParQAnswer::where('user_id', $user->id)->first();
        $this->assertNull($answer->parq_pregnant_or_possible);
        $this->assertNull($answer->parq_menstrual_change_or_stress_fracture);
        $this->assertFalse((bool) $user->fresh()->flagged_for_review);
    }

    public function test_parq_pregnancy_flag_true_flags_female_user_for_review(): void
    {
        $user = $this->makeUser('female');
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/onboarding/par-q', $this->baseParqPayload([
            'parq_pregnant_or_possible' => true,
        ]));

        $response->assertStatus(200);
        $this->assertTrue((bool) $user->fresh()->flagged_for_review);
        $this->assertTrue(ParQAnswer::where('user_id', $user->id)->first()->parq_pregnant_or_possible);
    }

    public function test_parq_eating_disorder_flag_true_flags_user_for_review_regardless_of_gender(): void
    {
        $user = $this->makeUser('male');
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/par-q', $this->baseParqPayload([
            'parq_eating_disorder_history' => true,
        ]))->assertStatus(200);

        $this->assertTrue((bool) $user->fresh()->flagged_for_review);
    }

    public function test_parq_menstrual_change_or_stress_fracture_flags_female_user_for_review(): void
    {
        $user = $this->makeUser('female');
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/par-q', $this->baseParqPayload([
            'parq_menstrual_change_or_stress_fracture' => true,
        ]))->assertStatus(200);

        $this->assertTrue((bool) $user->fresh()->flagged_for_review);
    }

    public function test_parq_all_clean_does_not_flag_user(): void
    {
        $user = $this->makeUser('female');
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/par-q', $this->baseParqPayload())->assertStatus(200);

        $this->assertFalse((bool) $user->fresh()->flagged_for_review);
    }

    // ═══ Nutrición -- disponibilidad de cocina ═══════════════════════

    private function baseNutritionPayload(array $overrides = []): array
    {
        return array_merge([
            'allergies_intolerances' => 'ninguna',
            'current_meals_per_day' => 3,
            'desired_meals_per_day' => 4,
            'typical_day_meals' => 'Desayuno, comida, cena',
            'cooking_minutes_per_meal' => 20,
            'cooking_skill_level' => 'intermediate',
            'cooks_for_others' => false,
        ], $overrides);
    }

    public function test_nutrition_questionnaire_requires_cooking_availability(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $payload = $this->baseNutritionPayload();
        unset($payload['cooking_minutes_per_meal']);

        $response = $this->postJson('/api/v1/onboarding/nutrition-questionnaire', $payload);

        $response->assertStatus(422);
        $this->assertArrayHasKey('cooking_minutes_per_meal', $response->json('errors', []));
    }

    public function test_nutrition_questionnaire_rejects_invalid_cooking_skill_level(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/onboarding/nutrition-questionnaire', $this->baseNutritionPayload([
            'cooking_skill_level' => 'expert_chef', // no es uno de los tres valores válidos
        ]));

        $response->assertStatus(422);
    }

    public function test_nutrition_questionnaire_persists_cooking_availability(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/nutrition-questionnaire', $this->baseNutritionPayload())
            ->assertStatus(200);

        $answer = NutritionQuestionnaireAnswer::where('user_id', $user->id)->first();
        $this->assertSame(20, $answer->cooking_minutes_per_meal);
        $this->assertSame('intermediate', $answer->cooking_skill_level);
        $this->assertFalse($answer->cooks_for_others);
    }

    // ═══ Disponibilidad de entrenamiento -- actualizable post-onboarding ═══

    public function test_availability_update_fails_without_prior_onboarding(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/onboarding/training-availability-update', [
            'training_days_per_week' => 4,
            'session_duration_preference' => '60',
        ]);

        $response->assertStatus(422);
    }

    public function test_availability_update_changes_days_and_duration_without_resubmitting_questionnaire(): void
    {
        $user = $this->makeUser();
        $answer = TrainingQuestionnaireAnswer::create([
            'user_id' => $user->id,
            'goal_type' => 'gain_muscle',
            'activity_level' => 'moderate',
            'lifestyle_type' => 'mostly_sitting',
            'training_experience_months' => 6,
            'training_days_per_week' => 3,
            'session_duration_preference' => '45',
            'training_mindset' => 'motivated',
            'previous_coaching' => 'self_trained',
            'current_routine_style' => 'structured',
            'weekly_split_preference' => 'upper_lower',
            'technique_level' => 5,
            'realistic_goal' => 'Ganar musculo',
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/onboarding/training-availability-update', [
            'training_days_per_week' => 5,
            'session_duration_preference' => '90',
        ]);

        $response->assertStatus(200);
        $answer->refresh();
        $this->assertSame(5, $answer->training_days_per_week);
        $this->assertSame('90', $answer->session_duration_preference);
        // el resto del cuestionario no se toca
        $this->assertSame('gain_muscle', $answer->goal_type);
        $this->assertSame(6, $answer->training_experience_months);
    }

    public function test_availability_update_rejects_invalid_duration_preference(): void
    {
        $user = $this->makeUser();
        TrainingQuestionnaireAnswer::create([
            'user_id' => $user->id,
            'goal_type' => 'maintain',
            'activity_level' => 'light',
            'lifestyle_type' => 'always_moving',
            'training_experience_months' => 12,
            'training_days_per_week' => 3,
            'session_duration_preference' => '45',
            'training_mindset' => 'calm',
            'previous_coaching' => 'in_person_coach',
            'current_routine_style' => 'improvised',
            'weekly_split_preference' => 'full_body',
            'technique_level' => 6,
            'realistic_goal' => 'Mantener forma',
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/onboarding/training-availability-update', [
            'training_days_per_week' => 4,
            'session_duration_preference' => '120', // no es una de las franjas válidas
        ]);

        $response->assertStatus(422);
    }
}
