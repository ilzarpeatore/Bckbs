<?php

namespace Tests\Feature;

use App\Models\ClientExerciseLog;
use App\Models\Exercise;
use App\Models\ProgramDayAssignment;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET client-session-feedback solo filtraba por completed_at IS NOT NULL,
 * sin comprobar si la sesión tenía series realmente registradas -- mismo
 * patrón de bug real que EmptySessionAlertService ya detecta (caso Ayoub,
 * 2026-09-25: sesiones finalizadas con volumen 0 y cero filas en
 * client_exercise_logs, pintadas en verde como "hechas"). Encontrado al
 * diseñar el Agente de Onboarding (AgenticdesignBS, ilzarpeatore/AgenticdesignBS):
 * decidir "el cliente ya empezó" a partir de esta lista sin este campo daría
 * una falsa señal de tranquilidad justo en ese caso.
 */
class ClientSessionFeedbackHasLoggedSetsTest extends TestCase
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

    private function makeAssignment(User $coach, User $client): ProgramDayAssignment
    {
        $program = TrainingProgram::create([
            'title'        => 'Macro',
            'coach_id'     => $coach->id,
            'client_id'    => $client->id,
            'num_weeks'    => 8,
            'fecha_inicio' => '2026-09-14',
            'activo'       => true,
        ]);
        $template = WorkoutTemplate::create(['coach_id' => $coach->id, 'title' => 'Torso A']);

        return ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'         => 1,
            'day_of_week'         => 1,
            'workout_template_id' => $template->id,
        ]);
    }

    public function test_session_with_real_logged_sets_is_flagged_as_such(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        $assignment = $this->makeAssignment($coach, $client);

        WorkoutSessionReview::create([
            'user_id'                   => $client->id,
            'program_day_assignment_id' => $assignment->id,
            'completed_at'              => now(),
            'volume_kg'                 => 1200,
        ]);

        $exercise = Exercise::create(['title' => 'Sentadilla']);
        ClientExerciseLog::create([
            'client_id'                  => $client->id,
            'exercise_id'                => $exercise->id,
            'program_day_assignment_id'  => $assignment->id,
            'performed_date'             => now()->toDateString(),
            'logged_sets'                => [['reps' => 10, 'weight' => 60]],
        ]);

        Sanctum::actingAs($coach, ['*']);
        $response = $this->getJson('/api/admin/client-session-feedback?client_id=' . $client->id);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.reviews.0.has_logged_sets'));
    }

    public function test_session_completed_with_zero_logged_sets_is_flagged_as_empty(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        $assignment = $this->makeAssignment($coach, $client);

        // Caso Ayoub: completed_at puesto, cero filas reales en client_exercise_logs.
        WorkoutSessionReview::create([
            'user_id'                   => $client->id,
            'program_day_assignment_id' => $assignment->id,
            'completed_at'              => now(),
            'volume_kg'                 => 0,
        ]);

        Sanctum::actingAs($coach, ['*']);
        $response = $this->getJson('/api/admin/client-session-feedback?client_id=' . $client->id);

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.reviews.0.has_logged_sets'));
    }

    public function test_loose_workout_without_program_day_assignment_defaults_to_true(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);

        WorkoutSessionReview::create([
            'user_id'                   => $client->id,
            'program_day_assignment_id' => null,
            'completed_at'              => now(),
            'volume_kg'                 => 0,
        ]);

        Sanctum::actingAs($coach, ['*']);
        $response = $this->getJson('/api/admin/client-session-feedback?client_id=' . $client->id);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.reviews.0.has_logged_sets'));
    }
}
