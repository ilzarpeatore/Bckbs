<?php

namespace Tests\Feature;

use App\Models\ClientLimitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cubre el encargo BRIEF_registro_alergias_intolerancias.md (proyecto
 * AgenticdesignBS): client_limitations gana una columna `severity` y el tipo
 * se amplía a intolerance/aversion/ethical_religious_preference. El guardrail
 * real es que `type=allergy` sin `severity` nunca se guarda -- ni al crear ni
 * al actualizar, aunque el cambio de tipo o el borrado de severity vengan en
 * peticiones separadas.
 */
class ClientLimitationSeverityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
    }

    private function makeCoach(): User
    {
        $coach = User::create([
            'first_name' => 'Coach',
            'last_name'  => 'Test',
            'username'   => 'coach_' . uniqid(),
            'email'      => uniqid() . '@example.test',
            'password'   => bcrypt('password'),
            'user_type'  => 'coach',
            'status'     => 'active',
            'login_type' => 'manual',
        ]);
        $coach->assignRole('admin'); // mismo patrón que IdorRegressionTest: 'coach' se provisiona con el rol admin.api

        return $coach;
    }

    private function makeClient(int $coachId): User
    {
        $client = User::create([
            'first_name' => 'Client',
            'last_name'  => 'Test',
            'username'   => 'client_' . uniqid(),
            'email'      => uniqid() . '@example.test',
            'password'   => bcrypt('password'),
            'user_type'  => 'user',
            'status'     => 'active',
            'login_type' => 'manual',
        ]);
        $client->forceFill(['coach_id' => $coachId])->save();

        return $client;
    }

    public function test_allergy_without_severity_is_rejected(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/client-limitation-store', [
            'client_id' => $client->id,
            'type' => 'allergy',
            'title' => 'Marisco',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'severity is required when type is allergy');
        $this->assertDatabaseCount('client_limitations', 0);
    }

    public function test_allergy_with_severity_is_accepted(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/client-limitation-store', [
            'client_id' => $client->id,
            'type' => 'allergy',
            'severity' => 'severe_anaphylaxis',
            'title' => 'Frutos secos',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('client_limitations', [
            'client_id' => $client->id,
            'type' => 'allergy',
            'severity' => 'severe_anaphylaxis',
        ]);
    }

    public function test_intolerance_without_severity_is_accepted(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/client-limitation-store', [
            'client_id' => $client->id,
            'type' => 'intolerance',
            'title' => 'Lactosa',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('client_limitations', [
            'client_id' => $client->id,
            'type' => 'intolerance',
            'severity' => null,
        ]);
    }

    public function test_new_types_from_the_brief_are_all_accepted(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        Sanctum::actingAs($coach, ['*']);

        foreach (['intolerance', 'aversion', 'ethical_religious_preference'] as $type) {
            $response = $this->postJson('/api/admin/client-limitation-store', [
                'client_id' => $client->id,
                'type' => $type,
                'title' => "Test $type",
            ]);
            $response->assertStatus(200);
        }
    }

    public function test_invalid_severity_value_is_rejected_by_validation(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/client-limitation-store', [
            'client_id' => $client->id,
            'type' => 'allergy',
            'severity' => 'super_grave', // no es uno de los tres valores válidos
            'title' => 'Marisco',
        ]);

        $response->assertStatus(422); // validación estándar de Laravel, no el guardrail custom
        $this->assertArrayHasKey('severity', $response->json('errors', []));
    }

    public function test_update_cannot_clear_severity_while_type_stays_allergy(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        $limitation = ClientLimitation::create([
            'client_id' => $client->id,
            'type' => 'allergy',
            'severity' => 'mild',
            'title' => 'Huevo',
        ]);
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/client-limitation-update', [
            'id' => $limitation->id,
            'severity' => '',
        ]);

        $response->assertStatus(422);
        $this->assertSame('mild', $limitation->fresh()->severity);
    }

    public function test_update_cannot_change_type_to_allergy_without_severity(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        $limitation = ClientLimitation::create([
            'client_id' => $client->id,
            'type' => 'limitation',
            'title' => 'Rodilla sensible',
        ]);
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/client-limitation-update', [
            'id' => $limitation->id,
            'type' => 'allergy',
        ]);

        $response->assertStatus(422);
        $this->assertSame('limitation', $limitation->fresh()->type);
    }

    public function test_update_can_change_type_to_allergy_when_severity_provided_together(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        $limitation = ClientLimitation::create([
            'client_id' => $client->id,
            'type' => 'limitation',
            'title' => 'Rodilla sensible',
        ]);
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/client-limitation-update', [
            'id' => $limitation->id,
            'type' => 'allergy',
            'severity' => 'moderate',
        ]);

        $response->assertStatus(200);
        $limitation->refresh();
        $this->assertSame('allergy', $limitation->type);
        $this->assertSame('moderate', $limitation->severity);
    }

    public function test_client_limitation_model_requires_severity_helper(): void
    {
        $allergy = new ClientLimitation(['type' => 'allergy']);
        $this->assertTrue($allergy->requiresSeverity());

        $intolerance = new ClientLimitation(['type' => 'intolerance']);
        $this->assertFalse($intolerance->requiresSeverity());
    }
}
