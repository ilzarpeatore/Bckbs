<?php

namespace Tests\Feature;

use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use App\Models\WorkoutTemplate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Entrenamientos personalizados creados por el propio cliente
 * (ClientCustomWorkoutController, 2026-09-24): se guardan en su calendario
 * personal y a partir de ahí se comportan como cualquier día del calendario.
 */
class ClientCustomWorkoutTest extends TestCase
{
    use RefreshDatabase;

    private int $exerciseA;
    private int $exerciseB;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00')); // jueves

        $this->exerciseA = DB::table('exercises')->insertGetId(['title' => 'Sentadilla', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->exerciseB = DB::table('exercises')->insertGetId(['title' => 'Press banca', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(?int $coachId = null): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name'  => 'User',
            'username'   => 'user_'.uniqid(),
            'email'      => uniqid().'@example.test',
            'password'   => bcrypt('password'),
            'user_type'  => 'user',
            'status'     => 'active',
            'login_type' => 'manual',
        ]);
        if ($coachId) {
            $user->forceFill(['coach_id' => $coachId])->save();
        }
        $user->assignRole('user');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title'  => 'Pierna en casa',
            'date'   => '2026-09-24',
            'blocks' => [
                ['title' => 'Calentamiento', 'exercises' => []],
                ['title' => 'Principal', 'exercises' => [
                    ['exercise_id' => $this->exerciseA, 'prescribed' => ['series' => '4', 'reps' => '8-10', 'carga' => '60', 'descanso' => '120', 'rir' => '2'], 'enabled_metrics' => ['reps', 'carga', 'descanso', 'rir']],
                    ['exercise_id' => $this->exerciseB, 'prescribed' => ['series' => '3', 'reps' => '10', 'rpe' => '8', 'bogus' => 'x'], 'enabled_metrics' => ['reps', 'carga', 'bogus']],
                ]],
            ],
        ], $overrides);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/my-custom-workouts', $this->payload())->assertStatus(401);
    }

    public function test_creates_workout_in_personal_calendar_and_it_shows_in_my_calendar(): void
    {
        $coach = $this->makeUser();
        $client = $this->makeUser($coach->id);
        Sanctum::actingAs($client);

        $res = $this->postJson('/api/v1/my-custom-workouts', $this->payload())->assertOk();
        $assignmentId = $res->json('data.assignments.0.assignment_id');
        $this->assertCount(1, $res->json('data.assignments'));
        $this->assertNull($res->json('data.series_uuid'));

        $assignment = ProgramDayAssignment::with('trainingProgram', 'workoutTemplate.blocks.exercises')->find($assignmentId);
        $this->assertTrue($assignment->trainingProgram->is_personal);
        $this->assertSame($client->id, (int) $assignment->trainingProgram->personal_client_id);
        $this->assertSame($coach->id, (int) $assignment->trainingProgram->coach_id);
        $this->assertSame('2026-09-24', $assignment->scheduled_date->toDateString());

        $template = $assignment->workoutTemplate;
        $this->assertSame($client->id, (int) $template->created_by_client_id);
        $this->assertSame($coach->id, (int) $template->coach_id);
        $this->assertFalse((bool) $template->is_public);
        // La sección vacía se descarta; solo queda "Principal".
        $this->assertCount(1, $template->blocks);
        $this->assertSame('Principal', $template->blocks[0]->title);
        $exercises = $template->blocks[0]->exercises->sortBy('sequence')->values();
        $this->assertSame(['series' => '4', 'reps' => '8-10', 'carga' => '60', 'descanso' => '120', 'rir' => '2'], $exercises[0]->prescribed);
        // Claves desconocidas descartadas; sin rir/rpe en métricas -> se añade rir.
        $this->assertArrayNotHasKey('bogus', $exercises[1]->prescribed);
        $this->assertSame(['reps', 'carga', 'rir'], $exercises[1]->enabled_metrics);

        // Aparece en el calendario del cliente, marcado como personalizado.
        $cal = $this->getJson('/api/v1/my-calendar?month=9&year=2026')->assertOk();
        $day = collect($cal->json('data.days'))->firstWhere('date', '2026-09-24');
        $this->assertCount(1, $day['workouts']);
        $this->assertSame($assignmentId, $day['workouts'][0]['assignment_id']);
        $this->assertTrue($day['workouts'][0]['is_custom']);
        $this->assertTrue($day['workouts'][0]['is_personal']);

        // Y se abre con el mismo endpoint de detalle que cualquier otro día.
        $detail = $this->getJson('/api/v1/my-calendar-day-detail?program_day_assignment_id='.$assignmentId)->assertOk();
        $this->assertCount(1, $detail->json('data.blocks'));

        // No aparece como "programa asignado" en el Home.
        $this->getJson('/api/v1/my-active-programs')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_repeat_weekly_creates_one_independent_copy_per_week(): void
    {
        $client = $this->makeUser();
        Sanctum::actingAs($client);

        $res = $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-21', 'repeat_weeks' => 3]))->assertOk();
        $rows = $res->json('data.assignments');
        $this->assertSame(['2026-09-21', '2026-09-28', '2026-10-05'], array_column($rows, 'date'));
        $this->assertNotNull($res->json('data.series_uuid'));
        // Cada ocurrencia con su propia plantilla (nunca compartida).
        $this->assertCount(3, array_unique(array_column($rows, 'workout_template_id')));

        foreach ($rows as $row) {
            $a = ProgramDayAssignment::find($row['assignment_id']);
            $this->assertSame(1, (int) $a->day_of_week); // lunes
        }
        // Sin coach: el propio cliente como coach_id (columna NOT NULL).
        $this->assertSame($client->id, (int) WorkoutTemplate::find($rows[0]['workout_template_id'])->coach_id);
    }

    public function test_validation_rejects_empty_workout_and_past_weeks(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/my-custom-workouts', $this->payload(['blocks' => [['title' => 'Vacía', 'exercises' => []]]]))
            ->assertStatus(422);
        $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-20']))
            ->assertStatus(422);
        $this->postJson('/api/v1/my-custom-workouts', $this->payload(['repeat_weeks' => 53]))
            ->assertStatus(422);
    }

    public function test_delete_following_removes_future_occurrences_but_keeps_completed_ones(): void
    {
        $client = $this->makeUser();
        Sanctum::actingAs($client);

        $rows = $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-21', 'repeat_weeks' => 4]))
            ->json('data.assignments');
        $ids = array_column($rows, 'assignment_id');

        // La 1ª ya está completada (no se toca aunque entre en el rango).
        WorkoutSessionReview::create(['user_id' => $client->id, 'program_day_assignment_id' => $ids[0], 'completed_at' => now()]);

        $this->postJson('/api/v1/my-custom-workouts-delete', ['program_day_assignment_id' => $ids[0], 'scope' => 'following'])
            ->assertOk()
            ->assertJsonPath('data.deleted', 3);

        $this->assertNotNull(ProgramDayAssignment::find($ids[0]));
        foreach (array_slice($ids, 1) as $id) {
            $this->assertNull(ProgramDayAssignment::find($id));
        }

        // Borrar solo un día ya completado -> 422.
        $this->postJson('/api/v1/my-custom-workouts-delete', ['program_day_assignment_id' => $ids[0]])->assertStatus(422);
    }

    public function test_cannot_delete_coach_assigned_or_other_clients_workouts(): void
    {
        $coach = $this->makeUser();
        $client = $this->makeUser($coach->id);
        $other = $this->makeUser();

        // Otro cliente crea el suyo.
        Sanctum::actingAs($other);
        $otherId = $this->postJson('/api/v1/my-custom-workouts', $this->payload())->json('data.assignments.0.assignment_id');

        // El coach asigna uno directo al calendario personal de $client.
        $program = \App\Http\Controllers\API\ClientProfileCalendarController::getOrCreatePersonalProgram($client->id, $coach->id);
        $coachTemplate = WorkoutTemplate::create(['coach_id' => $coach->id, 'title' => 'Del coach']);
        $coachAssignment = ProgramDayAssignment::create([
            'training_program_id' => $program->id, 'week_number' => 1, 'day_of_week' => 4,
            'workout_template_id' => $coachTemplate->id, 'scheduled_date' => '2026-09-24',
        ]);

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/my-custom-workouts-delete', ['program_day_assignment_id' => $coachAssignment->id])->assertStatus(403);
        $this->postJson('/api/v1/my-custom-workouts-delete', ['program_day_assignment_id' => $otherId])->assertStatus(403);
        $this->assertNotNull(ProgramDayAssignment::find($coachAssignment->id));
        $this->assertNotNull(ProgramDayAssignment::find($otherId));
    }

    public function test_active_programs_lists_assigned_programs_with_week_progress(): void
    {
        $coach = $this->makeUser();
        $client = $this->makeUser($coach->id);

        $program = TrainingProgram::create([
            'title' => 'Be Stronger — Macrociclo 2', 'coach_id' => $coach->id, 'num_weeks' => 8,
            'fecha_inicio' => '2026-09-14', 'activo' => true,
        ]);
        ProgramClientAssignment::create([
            'training_program_id' => $program->id, 'client_id' => $client->id,
            'start_date' => '2026-09-14', 'fecha_fin' => '2026-11-08', 'activo' => true,
        ]);
        $t = WorkoutTemplate::create(['coach_id' => $coach->id, 'title' => 'Pierna (S1)']);
        $a1 = ProgramDayAssignment::create(['training_program_id' => $program->id, 'week_number' => 2, 'day_of_week' => 1, 'workout_template_id' => $t->id]);
        ProgramDayAssignment::create(['training_program_id' => $program->id, 'week_number' => 2, 'day_of_week' => 3, 'workout_template_id' => $t->id]);
        ProgramDayAssignment::create(['training_program_id' => $program->id, 'week_number' => 3, 'day_of_week' => 1, 'workout_template_id' => $t->id]);
        WorkoutSessionReview::create(['user_id' => $client->id, 'program_day_assignment_id' => $a1->id, 'completed_at' => now()]);

        Sanctum::actingAs($client);
        $res = $this->getJson('/api/v1/my-active-programs')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Be Stronger — Macrociclo 2', $res->json('data.0.title'));
        $this->assertSame($program->id, $res->json('data.0.training_program_id'));
        $this->assertSame(2, $res->json('data.0.current_week'));
        $this->assertSame(2, $res->json('data.0.sessions_this_week'));
        $this->assertSame(1, $res->json('data.0.completed_this_week'));
    }
}
