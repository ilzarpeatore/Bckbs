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
 * Preguntas nuevas del onboarding (2026-09-29): lesión estructurada (PAR-Q),
 * deporte/evento, contexto de vida, material y referencias de fuerza
 * (entrenamiento) y nutrición práctica. Todas opcionales en el backend para
 * no romper versiones antiguas de la app.
 */
class OnboardingExtendedFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
    }

    private function makeUser(): User
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
            'gender' => 'male',
        ]);
        $user->assignRole('user');

        return $user;
    }

    private function parqPayload(array $overrides = []): array
    {
        return array_merge([
            'parq_heart_condition' => false,
            'parq_chest_pain_activity' => false,
            'parq_chest_pain_rest_last_month' => false,
            'parq_dizziness_balance' => false,
            'parq_bone_joint_problem' => false,
            'parq_bp_or_heart_medication' => false,
            'parq_reason_not_to_exercise' => false,
            'parq_eating_disorder_history' => false,
            'parq_fitness_level' => 5,
            'parq_goals' => 'Ganar fuerza',
        ], $overrides);
    }

    private function trainingPayload(array $overrides = []): array
    {
        return array_merge([
            'goal_type' => 'gain_muscle',
            'activity_level' => 'moderate',
            'lifestyle_type' => 'mostly_sitting',
            'training_experience_months' => 24,
            'training_days_per_week' => 4,
            'session_duration_preference' => '60',
            'training_mindset' => 'motivated',
            'previous_coaching' => 'self_trained',
            'current_routine_style' => 'structured',
            'weekly_split_preference' => 'upper_lower',
            'technique_level' => 7,
            'realistic_goal' => 'Torso-pierna 4 días',
        ], $overrides);
    }

    private function nutritionPayload(array $overrides = []): array
    {
        return array_merge([
            'allergies_intolerances' => 'Ninguna',
            'current_meals_per_day' => 3,
            'desired_meals_per_day' => 4,
            'typical_day_meals' => 'Desayuno, comida, cena',
            'cooking_minutes_per_meal' => 20,
            'cooking_skill_level' => 'intermediate',
            'cooks_for_others' => false,
        ], $overrides);
    }

    private function injuryFields(array $overrides = []): array
    {
        return array_merge([
            'injury_has' => true,
            'injury_zone' => 'knee',
            'injury_painful_movement' => 'Sentadilla profunda',
            'injury_phase' => 'recovering',
            'injury_worsens_with_impact' => 'yes',
            'injury_professional_clearance' => 'with_limits',
            'injury_other_notes' => 'Molestia leve de hombro',
        ], $overrides);
    }

    // ═══ PAR-Q: lesión estructurada ═════════════════════════════════════

    public function test_parq_persists_structured_injury(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/par-q', $this->parqPayload($this->injuryFields()))
            ->assertStatus(200);

        $answer = ParQAnswer::where('user_id', $user->id)->first();
        $this->assertTrue($answer->injury_has);
        $this->assertSame('knee', $answer->injury_zone);
        $this->assertSame('Sentadilla profunda', $answer->injury_painful_movement);
        $this->assertSame('recovering', $answer->injury_phase);
        $this->assertSame('yes', $answer->injury_worsens_with_impact);
        $this->assertSame('with_limits', $answer->injury_professional_clearance);
        $this->assertFalse((bool) $user->fresh()->flagged_for_review);
    }

    public function test_parq_flags_acute_injury_for_review(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/par-q', $this->parqPayload($this->injuryFields(['injury_phase' => 'acute'])))
            ->assertStatus(200);

        $this->assertTrue((bool) $user->fresh()->flagged_for_review);
    }

    public function test_parq_clears_injury_details_when_user_has_no_injury(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/par-q', $this->parqPayload($this->injuryFields()))->assertStatus(200);
        // Respondió "no" después, pero la app todavía envía los detalles viejos.
        $this->postJson('/api/v1/onboarding/par-q', $this->parqPayload($this->injuryFields(['injury_has' => false])))
            ->assertStatus(200);

        $answer = ParQAnswer::where('user_id', $user->id)->first();
        $this->assertFalse($answer->injury_has);
        $this->assertNull($answer->injury_zone);
        $this->assertNull($answer->injury_painful_movement);
        $this->assertNull($answer->injury_phase);
    }

    public function test_parq_without_new_fields_keeps_existing_injury(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/par-q', $this->parqPayload($this->injuryFields()))->assertStatus(200);
        // Una versión antigua de la app no envía ningún campo de lesión.
        $this->postJson('/api/v1/onboarding/par-q', $this->parqPayload(['parq_fitness_level' => 7]))->assertStatus(200);

        $answer = ParQAnswer::where('user_id', $user->id)->first();
        $this->assertSame(7, $answer->parq_fitness_level);
        $this->assertSame('knee', $answer->injury_zone);
    }

    public function test_parq_rejects_unknown_injury_zone(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/onboarding/par-q', $this->parqPayload($this->injuryFields(['injury_zone' => 'eyebrow'])));

        $response->assertStatus(422);
        $this->assertArrayHasKey('injury_zone', $response->json('errors', []));
    }

    // ═══ Entrenamiento: deporte/evento, vida, material, fuerza ══════════

    public function test_training_persists_extended_context(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/training-questionnaire', $this->trainingPayload([
            'practices_other_sport' => true,
            'other_sport_description' => 'Running 3 días, 25 km/semana',
            'has_target_event' => true,
            'target_event_description' => 'HYROX Madrid',
            'target_event_date' => '2027-03-14',
            'work_schedule' => 'rotating_shifts',
            'training_time_of_day' => 'evening',
            'sleep_hours' => 6,
            'sleep_regularity' => 'irregular',
            'stress_level' => 8,
            'training_location' => 'home',
            'home_equipment' => ['dumbbells', 'bench', 'bands'],
            'equipment_notes' => 'Mancuernas hasta 20 kg',
            'strength_squat_kg' => 80,
            'strength_squat_reps' => 8,
            'strength_db_bench_kg' => 22.5,
            'strength_db_bench_reps' => 10,
        ]))->assertStatus(200);

        $answer = TrainingQuestionnaireAnswer::where('user_id', $user->id)->first();
        $this->assertTrue($answer->practices_other_sport);
        $this->assertSame('HYROX Madrid', $answer->target_event_description);
        $this->assertSame('2027-03-14', $answer->target_event_date->format('Y-m-d'));
        $this->assertSame('rotating_shifts', $answer->work_schedule);
        $this->assertSame(6, $answer->sleep_hours);
        $this->assertSame(8, $answer->stress_level);
        $this->assertSame(['dumbbells', 'bench', 'bands'], $answer->home_equipment);
        $this->assertSame(80.0, $answer->strength_squat_kg);
        $this->assertSame(22.5, $answer->strength_db_bench_kg);
        $this->assertNull($answer->strength_deadlift_kg);
    }

    public function test_training_clears_event_details_when_no_event(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/training-questionnaire', $this->trainingPayload([
            'has_target_event' => false,
            'target_event_description' => 'Vieja respuesta',
            'target_event_date' => '2027-03-14',
        ]))->assertStatus(200);

        $answer = TrainingQuestionnaireAnswer::where('user_id', $user->id)->first();
        $this->assertFalse($answer->has_target_event);
        $this->assertNull($answer->target_event_description);
        $this->assertNull($answer->target_event_date);
    }

    public function test_training_rejects_unknown_equipment(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/onboarding/training-questionnaire', $this->trainingPayload([
            'training_location' => 'home',
            'home_equipment' => ['dumbbells', 'jacuzzi'],
        ]));

        $response->assertStatus(422);
    }

    public function test_training_legacy_payload_still_accepted(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/training-questionnaire', $this->trainingPayload())->assertStatus(200);

        $answer = TrainingQuestionnaireAnswer::where('user_id', $user->id)->first();
        $this->assertNull($answer->training_location);
        $this->assertNull($answer->home_equipment);
    }

    // ═══ Nutrición práctica ═════════════════════════════════════════════

    public function test_nutrition_persists_practical_fields(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/nutrition-questionnaire', $this->nutritionPayload([
            'weekly_food_budget' => '40_70',
            'meals_away_from_home' => 'tupper',
            'meal_schedule' => '7:30, 14:00, 21:30',
            'intermittent_fasting' => false,
            'alcohol_frequency' => 'weekends',
            'water_intake' => '1_5_2l',
            'previous_diets' => 'Keto 3 meses, efecto rebote',
        ]))->assertStatus(200);

        $answer = NutritionQuestionnaireAnswer::where('user_id', $user->id)->first();
        $this->assertSame('40_70', $answer->weekly_food_budget);
        $this->assertSame('tupper', $answer->meals_away_from_home);
        $this->assertFalse($answer->intermittent_fasting);
        $this->assertSame('weekends', $answer->alcohol_frequency);
        $this->assertSame('1_5_2l', $answer->water_intake);
    }

    public function test_nutrition_rejects_invalid_alcohol_frequency(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/onboarding/nutrition-questionnaire', $this->nutritionPayload([
            'alcohol_frequency' => 'a_veces',
        ]))->assertStatus(422);
    }
}
