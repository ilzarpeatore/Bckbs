<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `Form::recurrence` solo aceptaba daily/weekly/monthly pese a que la
 * migración de creación de la tabla ya documentaba "biweekly" como opción
 * (comentario nunca implementado) -- encontrado al diseñar un check-in
 * quincenal real para un cliente (2026-09-27). Cubre que el backend acepte
 * la recurrencia y que el cálculo de "pendiente" (`is_due`) use una ventana
 * móvil de 14 días, no la rama `default` (que trataría biweekly como daily).
 */
class FormBiweeklyRecurrenceTest extends TestCase
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
        $coach->assignRole('admin');

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

    public function test_admin_can_create_form_with_biweekly_recurrence(): void
    {
        $coach = $this->makeCoach();
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/admin-form-store', [
            'title'      => 'Check-in de satisfacción quincenal',
            'recurrence' => 'biweekly',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.recurrence', 'biweekly');
        $this->assertDatabaseHas('forms', ['title' => 'Check-in de satisfacción quincenal', 'recurrence' => 'biweekly']);
    }

    public function test_invalid_recurrence_still_rejected(): void
    {
        $coach = $this->makeCoach();
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/admin-form-store', [
            'title'      => 'Formulario inválido',
            'recurrence' => 'fortnightly',
        ]);

        $response->assertStatus(422);
    }

    public function test_biweekly_checkin_not_due_when_submitted_5_days_ago(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);

        $form = Form::create(['coach_id' => $coach->id, 'title' => 'Quincenal', 'recurrence' => 'biweekly']);
        $assignment = FormAssignment::create(['form_id' => $form->id, 'client_id' => $client->id, 'active' => true]);
        FormSubmission::create([
            'form_assignment_id' => $assignment->id,
            'submitted_at'       => now()->subDays(5),
        ]);

        Sanctum::actingAs($client, ['*']);
        $response = $this->getJson('/api/form-assigned-list');

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.0.is_due'), 'No debería estar pendiente a los 5 días de un check-in quincenal.');
    }

    public function test_biweekly_checkin_due_when_submitted_20_days_ago(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);

        $form = Form::create(['coach_id' => $coach->id, 'title' => 'Quincenal', 'recurrence' => 'biweekly']);
        $assignment = FormAssignment::create(['form_id' => $form->id, 'client_id' => $client->id, 'active' => true]);
        FormSubmission::create([
            'form_assignment_id' => $assignment->id,
            'submitted_at'       => now()->subDays(20),
        ]);

        Sanctum::actingAs($client, ['*']);
        $response = $this->getJson('/api/form-assigned-list');

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.0.is_due'), 'Debería estar pendiente a los 20 días de un check-in quincenal.');
    }
}
