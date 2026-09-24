<?php

namespace Tests\Feature;

use App\Http\Controllers\API\ClientCalendarController;
use App\Http\Controllers\API\ClientProfileCalendarController;
use App\Jobs\EvaluateSessionProgressionRules;
use App\Jobs\ProcessSessionInterpretation;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use App\Services\CalendarDateMapper;
use App\Services\SessionProgressionRuleEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
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
        $this->assertNotNull($res->json('data.series_uuid'));

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
        $this->assertFalse($day['workouts'][0]['is_repeating']);
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
        $this->assertSame('program', $res->json('data.0.kind'));
        $this->assertSame($program->id, $res->json('data.0.training_program_id'));
        $this->assertSame(2, $res->json('data.0.current_week'));
        $this->assertSame(2, $res->json('data.0.sessions_this_week'));
        $this->assertSame(1, $res->json('data.0.completed_this_week'));
    }

    public function test_same_client_request_id_does_not_duplicate(): void
    {
        $client = $this->makeUser();
        Sanctum::actingAs($client);

        $payload = $this->payload(['date' => '2026-09-21', 'repeat_weeks' => 3, 'client_request_id' => 'abc_DEF-123']);
        $first = $this->postJson('/api/v1/my-custom-workouts', $payload)->assertOk();
        $second = $this->postJson('/api/v1/my-custom-workouts', $payload)->assertOk();

        $this->assertFalse($first->json('replayed'));
        $this->assertTrue($second->json('replayed'));
        $this->assertSame($first->json('data.assignments'), $second->json('data.assignments'));
        $this->assertSame(3, WorkoutTemplate::where('created_by_client_id', $client->id)->count());

        // Y el calendario marca las ocurrencias como repetidas.
        $cal = $this->getJson('/api/v1/my-calendar?month=9&year=2026')->assertOk();
        $day = collect($cal->json('data.days'))->firstWhere('date', '2026-09-28');
        $this->assertTrue($day['workouts'][0]['is_repeating']);

        // Un id de otro cliente con el mismo valor no le devuelve nada ajeno.
        Sanctum::actingAs($this->makeUser());
        $this->postJson('/api/v1/my-custom-workouts', $payload)->assertOk()->assertJsonPath('replayed', false);
    }

    public function test_prescribed_values_are_normalized(): void
    {
        Sanctum::actingAs($this->makeUser());

        $res = $this->postJson('/api/v1/my-custom-workouts', $this->payload(['blocks' => [['title' => 'X', 'exercises' => [
            ['exercise_id' => $this->exerciseA, 'prescribed' => ['series' => '99', 'reps' => '12-8', 'carga' => '22,5', 'descanso' => '1:30', 'rir' => '15']],
            ['exercise_id' => $this->exerciseB, 'prescribed' => ['series' => 'abc', 'reps' => '8-', 'carga' => '1.2.3', 'descanso' => '-', 'rpe' => '0']],
        ]]]]))->assertOk();

        $template = WorkoutTemplate::with('blocks.exercises')->find($res->json('data.assignments.0.workout_template_id'));
        $ex = $template->blocks[0]->exercises->sortBy('sequence')->values();
        $this->assertSame(['series' => '20', 'reps' => '8-12', 'carga' => '22.5', 'descanso' => '90', 'rir' => '10'], $ex[0]->prescribed);
        // Basura descartada o saneada, nunca guardada tal cual.
        $this->assertSame(['series' => '3', 'reps' => '8', 'rpe' => '1'], $ex[1]->prescribed);
    }

    public function test_limits_and_spanish_messages(): void
    {
        Sanctum::actingAs($this->makeUser());

        $many = array_fill(0, 41, ['exercise_id' => $this->exerciseA]);
        $this->postJson('/api/v1/my-custom-workouts', $this->payload(['blocks' => [['title' => 'X', 'exercises' => $many]]]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Como máximo puedes tener 40 ejercicios por sección.');

        $blocks = [];
        foreach (range(1, 2) as $i) $blocks[] = ['title' => "S$i", 'exercises' => array_fill(0, 31, ['exercise_id' => $this->exerciseA])];
        $this->postJson('/api/v1/my-custom-workouts', $this->payload(['blocks' => $blocks]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Como máximo puedes tener 60 ejercicios en un entrenamiento.');

        $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-13']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solo puedes crear entrenamientos a partir de la semana en curso.');

        $this->postJson('/api/v1/my-custom-workouts', $this->payload(['blocks' => [['title' => 'X', 'exercises' => [['exercise_id' => 999999]]]]]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Uno de los ejercicios ya no existe. Quítalo y vuelve a añadirlo.');
    }

    public function test_monday_in_utc_still_accepts_the_local_current_week(): void
    {
        // Lunes 00:30 UTC: un cliente en UTC-5 todavía está en domingo y su
        // "semana en curso" empieza el lunes anterior.
        Carbon::setTestNow(Carbon::parse('2026-09-28 00:30:00'));
        Sanctum::actingAs($this->makeUser());
        $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-22']))->assertOk();
    }

    /** Lo que hace assignDirect() del panel: plantilla del coach en el calendario personal del cliente. */
    private function coachAssign(User $client, User $coach, string $date, array $exerciseIds = []): ProgramDayAssignment
    {
        $program = ClientProfileCalendarController::getOrCreatePersonalProgram($client->id, $coach->id);
        $template = WorkoutTemplate::create(['coach_id' => $coach->id, 'title' => 'Del coach '.$date]);
        $block = WorkoutTemplateBlock::create(['workout_template_id' => $template->id, 'title' => 'Principal', 'order' => 1]);
        foreach (array_values($exerciseIds) as $i => $exerciseId) {
            WorkoutTemplateExercise::create([
                'workout_template_block_id' => $block->id, 'exercise_id' => $exerciseId, 'sequence' => $i + 1,
                'prescribed' => ['series' => '3', 'reps' => '10', 'carga' => '50'], 'enabled_metrics' => ['reps', 'carga', 'rir'],
            ]);
        }
        $wd = (new CalendarDateMapper())->toWeekAndDay(Carbon::parse(ClientProfileCalendarController::PERSONAL_ANCHOR_DATE), Carbon::parse($date));

        return ProgramDayAssignment::create([
            'training_program_id' => $program->id, 'week_number' => $wd['week_number'], 'day_of_week' => $wd['day_of_week'],
            'workout_template_id' => $template->id, 'scheduled_date' => $date,
        ]);
    }

    private function makePaidClient(User $coach): User
    {
        $client = $this->makeUser($coach->id);
        $client->forceFill(['is_personal_client' => true])->save();

        return $client->fresh();
    }

    public function test_custom_session_does_not_feed_the_load_engine_but_coach_session_does(): void
    {
        $coach = $this->makeUser();
        $client = $this->makePaidClient($coach);
        $this->assertSame('personal', $client->access_tier);
        Sanctum::actingAs($client);

        $customId = $this->postJson('/api/v1/my-custom-workouts', $this->payload())->json('data.assignments.0.assignment_id');
        $coachPda = $this->coachAssign($client, $coach, '2026-09-24', [$this->exerciseA]);

        Bus::fake([ProcessSessionInterpretation::class, EvaluateSessionProgressionRules::class]);

        $this->postJson('/api/v1/my-calendar-finish-session', ['program_day_assignment_id' => $customId, 'duration_seconds' => 1800])
            ->assertOk();
        Bus::assertNotDispatched(ProcessSessionInterpretation::class);
        Bus::assertNotDispatched(EvaluateSessionProgressionRules::class);
        // El resto del cierre sigue igual: la review existe.
        $this->assertTrue(WorkoutSessionReview::where('program_day_assignment_id', $customId)->exists());

        $this->postJson('/api/v1/my-calendar-finish-session', ['program_day_assignment_id' => $coachPda->id, 'duration_seconds' => 1800])
            ->assertOk();
        Bus::assertDispatched(ProcessSessionInterpretation::class);
        Bus::assertDispatched(EvaluateSessionProgressionRules::class);
    }

    public function test_load_engine_never_targets_a_custom_workout_as_next_session(): void
    {
        $coach = $this->makeUser();
        $client = $this->makePaidClient($coach);
        Sanctum::actingAs($client);

        // Personalizado mañana con el ejercicio A; sesión del coach pasado mañana.
        $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-25']))->assertOk();
        $engine = app(SessionProgressionRuleEngine::class);
        $this->assertNull($engine->resolveNextAssignmentForExercise($client->id, $this->exerciseA));

        $coachPda = $this->coachAssign($client, $coach, '2026-09-26', [$this->exerciseA]);
        $this->assertSame($coachPda->id, $engine->resolveNextAssignmentForExercise($client->id, $this->exerciseA)?->id);
    }

    public function test_custom_workouts_never_count_as_scheduled_for_adherence(): void
    {
        $coach = $this->makeUser();
        $client = $this->makeUser($coach->id);
        Sanctum::actingAs($client);

        // Martes: sesión del coach + un personalizado el mismo día (antes
        // podía "tapar" a la del coach). Miércoles: personalizado saltado.
        $coachPda = $this->coachAssign($client, $coach, '2026-09-22', [$this->exerciseA]);
        $sameDayCustom = $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-22']))->json('data.assignments.0.assignment_id');
        $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-23']))->assertOk();

        $adherence = ClientCalendarController::computeAdherence($client->id, 7);
        $this->assertSame('program', $adherence['mode']);
        $this->assertSame(1, $adherence['scheduledCount']);   // solo la del coach
        $this->assertSame(0, $adherence['completedCount']);

        // Completar el personalizado no mueve el ratio...
        WorkoutSessionReview::create(['user_id' => $client->id, 'program_day_assignment_id' => $sameDayCustom, 'completed_at' => now()]);
        $adherence = ClientCalendarController::computeAdherence($client->id, 7);
        $this->assertSame(0, $adherence['completedCount']);
        $this->assertEquals(0, $adherence['ratio']);

        // ...completar la del coach sí, y el personalizado saltado del
        // miércoles no baja el 100% ni corta la racha.
        WorkoutSessionReview::create(['user_id' => $client->id, 'program_day_assignment_id' => $coachPda->id, 'completed_at' => now()]);
        $adherence = ClientCalendarController::computeAdherence($client->id, 7);
        $this->assertSame(1, $adherence['scheduledCount']);
        $this->assertSame(1, $adherence['completedCount']);
        $this->assertEquals(1, $adherence['ratio']);
        $this->assertSame(1, $adherence['currentStreak']);
        $this->assertSame([['date' => '2026-09-22', 'completed' => true]], $adherence['days']);
    }

    // ───── my-active-programs: fecha local (?today) ─────

    public function test_active_programs_uses_the_local_today_within_one_day(): void
    {
        // Domingo 23:30 UTC = lunes 01:30 en España: ya es la semana 3.
        Carbon::setTestNow(Carbon::parse('2026-09-27 23:30:00'));
        $coach = $this->makeUser();
        $client = $this->makeUser($coach->id);
        $program = TrainingProgram::create(['title' => 'Macro', 'coach_id' => $coach->id, 'num_weeks' => 8, 'fecha_inicio' => '2026-09-14', 'activo' => true]);
        ProgramClientAssignment::create(['training_program_id' => $program->id, 'client_id' => $client->id, 'start_date' => '2026-09-14', 'fecha_fin' => '2026-11-08', 'activo' => true]);
        Sanctum::actingAs($client);

        $this->getJson('/api/v1/my-active-programs')->assertOk()->assertJsonPath('data.0.current_week', 2);
        $this->getJson('/api/v1/my-active-programs?today=2026-09-28')->assertOk()->assertJsonPath('data.0.current_week', 3);
        // Fuera de ±1 día, o mal formada: se ignora (fecha del servidor), sin error.
        $this->getJson('/api/v1/my-active-programs?today=2026-10-05')->assertOk()->assertJsonPath('data.0.current_week', 2);
        $this->getJson('/api/v1/my-active-programs?today=28-09-2026')->assertOk()->assertJsonPath('data.0.current_week', 2);
        $this->getJson('/api/v1/my-active-programs?today=2026-02-31')->assertOk()->assertJsonPath('data.0.current_week', 2);
    }

    // ───── my-active-programs: "Plan de tu entrenador" ─────

    public function test_active_programs_adds_coach_calendar_item_for_direct_assignments(): void
    {
        $coach = $this->makeUser();
        $client = $this->makeUser($coach->id);
        Sanctum::actingAs($client);

        // Semana en curso (21-27 sep): 2 sesiones del coach (una hecha) + un
        // personalizado (no cuenta). La de la semana que viene tampoco.
        $done = $this->coachAssign($client, $coach, '2026-09-22', [$this->exerciseA]);
        $this->coachAssign($client, $coach, '2026-09-25', [$this->exerciseA]);
        $this->coachAssign($client, $coach, '2026-09-29', [$this->exerciseA]);
        $custom = $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-23']))->json('data.assignments.0.assignment_id');
        WorkoutSessionReview::create(['user_id' => $client->id, 'program_day_assignment_id' => $done->id, 'completed_at' => now()]);
        WorkoutSessionReview::create(['user_id' => $client->id, 'program_day_assignment_id' => $custom, 'completed_at' => now()]);

        $res = $this->getJson('/api/v1/my-active-programs')->assertOk()->assertJsonCount(1, 'data');
        $item = $res->json('data.0');
        $program = TrainingProgram::where('personal_client_id', $client->id)->where('is_personal', true)->first();
        $pca = ProgramClientAssignment::where('training_program_id', $program->id)->first();
        $this->assertSame([
            'kind' => 'coach_calendar', 'program_client_assignment_id' => $pca->id, 'training_program_id' => $program->id,
            'title' => 'Plan de tu entrenador', 'start_date' => '2020-01-06', 'end_date' => null, 'num_weeks' => 0,
            'current_week' => 0, 'sessions_this_week' => 2, 'completed_this_week' => 1,
        ], $item);

        // Con un programa real también: el programa primero, el item extra al final.
        $real = TrainingProgram::create(['title' => 'Macro', 'coach_id' => $coach->id, 'num_weeks' => 8, 'fecha_inicio' => '2026-09-14', 'activo' => true]);
        ProgramClientAssignment::create(['training_program_id' => $real->id, 'client_id' => $client->id, 'start_date' => '2026-09-14', 'activo' => true]);
        $res = $this->getJson('/api/v1/my-active-programs')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(['program', 'coach_calendar'], array_column($res->json('data'), 'kind'));
    }

    public function test_no_coach_calendar_item_without_coach_sessions_this_week(): void
    {
        $coach = $this->makeUser();
        $client = $this->makeUser($coach->id);
        Sanctum::actingAs($client);

        $this->coachAssign($client, $coach, '2026-09-29', [$this->exerciseA]); // semana que viene
        $this->postJson('/api/v1/my-custom-workouts', $this->payload())->assertOk(); // solo personalizados esta semana
        // El entrenamiento demo del registro tampoco es un plan del coach.
        $demo = $this->coachAssign($client, $coach, '2026-09-24');
        $demo->workoutTemplate->forceFill(['is_demo' => true])->save();

        $this->getJson('/api/v1/my-active-programs')->assertOk()->assertJsonCount(0, 'data');
        // ...pero con ?today del lunes siguiente (fuera de ±1 día) se sigue usando la semana del servidor.
        $this->getJson('/api/v1/my-active-programs?today=2026-09-29')->assertOk()->assertJsonCount(0, 'data');
    }

    // ───── Editar un personalizado ─────

    private function editPayload(array $overrides = []): array
    {
        return array_merge([
            'title'  => 'Pierna editada',
            'blocks' => [
                ['title' => 'Nueva', 'exercises' => [
                    ['exercise_id' => $this->exerciseB, 'prescribed' => ['series' => '5', 'reps' => '5', 'carga' => '80,5'], 'enabled_metrics' => ['reps', 'carga']],
                ]],
                ['title' => '', 'exercises' => [
                    ['exercise_id' => $this->exerciseA, 'notes' => 'Lento'],
                ]],
            ],
        ], $overrides);
    }

    public function test_detail_returns_the_editable_structure_only_to_its_owner(): void
    {
        $coach = $this->makeUser();
        $client = $this->makeUser($coach->id);
        $other = $this->makeUser();
        Sanctum::actingAs($client);

        $rows = $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-28', 'repeat_weeks' => 2]))->json('data.assignments');
        $id = $rows[0]['assignment_id'];

        $res = $this->getJson('/api/v1/my-custom-workout-detail?program_day_assignment_id='.$id)->assertOk();
        $this->assertSame($id, $res->json('data.assignment_id'));
        $this->assertSame('2026-09-28', $res->json('data.date'));
        $this->assertSame('Pierna en casa', $res->json('data.title'));
        $this->assertTrue($res->json('data.is_repeating'));
        $this->assertFalse($res->json('data.is_completed'));
        $this->assertCount(1, $res->json('data.blocks'));
        $this->assertSame('Principal', $res->json('data.blocks.0.title'));
        $ex = $res->json('data.blocks.0.exercises');
        $this->assertSame([$this->exerciseA, $this->exerciseB], array_column($ex, 'exercise_id'));
        $this->assertSame('Sentadilla', $ex[0]['title']);
        $this->assertNull($ex[0]['image']);
        $this->assertSame(['series' => '4', 'reps' => '8-10', 'carga' => '60', 'descanso' => '120', 'rir' => '2'], $ex[0]['prescribed']);
        $this->assertSame(['reps', 'carga', 'descanso', 'rir'], $ex[0]['enabled_metrics']);
        $this->assertNull($ex[0]['notes']);

        WorkoutSessionReview::create(['user_id' => $client->id, 'program_day_assignment_id' => $id, 'completed_at' => now()]);
        $this->getJson('/api/v1/my-custom-workout-detail?program_day_assignment_id='.$id)->assertOk()->assertJsonPath('data.is_completed', true);

        // Del coach (en su propio calendario personal) -> 403.
        $coachPda = $this->coachAssign($client, $coach, '2026-09-25', [$this->exerciseA]);
        $this->getJson('/api/v1/my-custom-workout-detail?program_day_assignment_id='.$coachPda->id)
            ->assertStatus(403)->assertJsonPath('message', 'Solo puedes editar entrenamientos que hayas creado tú.');
        // No existe -> 404.
        $this->getJson('/api/v1/my-custom-workout-detail?program_day_assignment_id=999999')->assertStatus(404);
        // De otro cliente -> 403.
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/my-custom-workout-detail?program_day_assignment_id='.$id)->assertStatus(403);
    }

    public function test_update_single_replaces_title_and_structure_of_that_occurrence_only(): void
    {
        $client = $this->makeUser();
        Sanctum::actingAs($client);
        $rows = $this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-24', 'repeat_weeks' => 2]))->json('data.assignments');
        [$first, $second] = array_column($rows, 'assignment_id');

        // Una serie ya registrada en la sesión (empezada, sin cerrar).
        $oldWte = WorkoutTemplateExercise::whereHas('block', fn ($q) => $q->where('workout_template_id', $rows[0]['workout_template_id']))->orderBy('sequence')->first();
        $this->postJson('/api/v1/my-calendar-log-sets', [
            'workout_template_exercise_id' => $oldWte->id, 'program_day_assignment_id' => $first,
            'logged_sets' => [['reps' => 10, 'carga' => 50, 'rir' => 2]],
        ])->assertOk();

        $this->postJson('/api/v1/my-custom-workouts-update', $this->editPayload(['program_day_assignment_id' => $first, 'scope' => 'single']))
            ->assertOk()
            ->assertJsonPath('message', 'Entrenamiento actualizado.')
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped_completed', 0);

        $detail = $this->getJson('/api/v1/my-custom-workout-detail?program_day_assignment_id='.$first)->assertOk();
        $this->assertSame('Pierna editada', $detail->json('data.title'));
        $this->assertSame(['Nueva', 'Sección 2'], array_column($detail->json('data.blocks'), 'title'));
        // Misma normalización que store(): coma decimal, rir obligatorio, series por defecto.
        $this->assertSame(['series' => '5', 'reps' => '5', 'carga' => '80.5'], $detail->json('data.blocks.0.exercises.0.prescribed'));
        $this->assertSame(['reps', 'carga', 'rir'], $detail->json('data.blocks.0.exercises.0.enabled_metrics'));
        $this->assertSame(['series' => '3'], $detail->json('data.blocks.1.exercises.0.prescribed'));
        $this->assertSame('Lento', $detail->json('data.blocks.1.exercises.0.notes'));
        // La misma plantilla (no se comparte ni se crea otra) y la sesión en vivo la ve igual.
        $this->assertSame($rows[0]['workout_template_id'], ProgramDayAssignment::find($first)->workout_template_id);
        $day = $this->getJson('/api/v1/my-calendar-day-detail?program_day_assignment_id='.$first)->assertOk();
        $this->assertSame($this->exerciseB, $day->json('data.blocks.0.exercises.0.exercise_id'));

        // Bloques/ejercicios viejos: soft delete -> la serie ya registrada sigue ahí.
        $this->assertSoftDeleted('workout_template_exercises', ['id' => $oldWte->id]);
        $this->assertSame(1, \App\Models\ClientExerciseLog::where('workout_template_exercise_id', $oldWte->id)->count());

        // La otra ocurrencia de la serie, intacta.
        $this->getJson('/api/v1/my-custom-workout-detail?program_day_assignment_id='.$second)
            ->assertOk()->assertJsonPath('data.title', 'Pierna en casa');
    }

    public function test_update_following_skips_completed_and_earlier_occurrences(): void
    {
        $client = $this->makeUser();
        Sanctum::actingAs($client);
        $ids = array_column($this->postJson('/api/v1/my-custom-workouts', $this->payload(['date' => '2026-09-21', 'repeat_weeks' => 4]))->json('data.assignments'), 'assignment_id');
        // La 3ª ya está completada.
        WorkoutSessionReview::create(['user_id' => $client->id, 'program_day_assignment_id' => $ids[2], 'completed_at' => now()]);

        $this->postJson('/api/v1/my-custom-workouts-update', $this->editPayload(['program_day_assignment_id' => $ids[1], 'scope' => 'following']))
            ->assertOk()
            ->assertJsonPath('message', 'Se han actualizado 2 entrenamientos.')
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.skipped_completed', 1);

        $titles = collect($ids)->map(fn ($id) => ProgramDayAssignment::with('workoutTemplate')->find($id)->workoutTemplate->title)->all();
        $this->assertSame(['Pierna en casa', 'Pierna editada', 'Pierna en casa', 'Pierna editada'], $titles);
        // Cada ocurrencia sigue con su propia plantilla.
        $this->assertCount(4, collect($ids)->map(fn ($id) => ProgramDayAssignment::find($id)->workout_template_id)->unique());

        // Solo la completada como objetivo -> 422, sin tocarla.
        $this->postJson('/api/v1/my-custom-workouts-update', $this->editPayload(['program_day_assignment_id' => $ids[2]]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este entrenamiento ya está completado y no se puede editar.');
        $this->assertSame('Pierna en casa', ProgramDayAssignment::with('workoutTemplate')->find($ids[2])->workoutTemplate->title);
    }

    public function test_update_rejects_foreign_workouts_and_reuses_store_validation(): void
    {
        $coach = $this->makeUser();
        $client = $this->makeUser($coach->id);
        $other = $this->makeUser();

        Sanctum::actingAs($other);
        $otherId = $this->postJson('/api/v1/my-custom-workouts', $this->payload())->json('data.assignments.0.assignment_id');

        Sanctum::actingAs($client);
        $coachPda = $this->coachAssign($client, $coach, '2026-09-25', [$this->exerciseA]);
        $this->postJson('/api/v1/my-custom-workouts-update', $this->editPayload(['program_day_assignment_id' => $coachPda->id]))->assertStatus(403);
        $this->postJson('/api/v1/my-custom-workouts-update', $this->editPayload(['program_day_assignment_id' => $otherId]))->assertStatus(403);
        $this->postJson('/api/v1/my-custom-workouts-update', $this->editPayload(['program_day_assignment_id' => 999999]))->assertStatus(404);
        $this->assertSame('Del coach 2026-09-25', $coachPda->workoutTemplate->fresh()->title);
        $this->assertSame('Pierna en casa', ProgramDayAssignment::with('workoutTemplate')->find($otherId)->workoutTemplate->title);

        $mine = $this->postJson('/api/v1/my-custom-workouts', $this->payload())->json('data.assignments.0.assignment_id');
        $this->postJson('/api/v1/my-custom-workouts-update', $this->editPayload(['program_day_assignment_id' => $mine, 'blocks' => [['title' => 'X', 'exercises' => []]]]))
            ->assertStatus(422)->assertJsonPath('message', 'Añade al menos un ejercicio al entrenamiento.');
        $this->postJson('/api/v1/my-custom-workouts-update', $this->editPayload(['program_day_assignment_id' => $mine, 'title' => '']))
            ->assertStatus(422)->assertJsonPath('message', 'El entrenamiento necesita un nombre.');
        $many = array_fill(0, 41, ['exercise_id' => $this->exerciseA]);
        $this->postJson('/api/v1/my-custom-workouts-update', $this->editPayload(['program_day_assignment_id' => $mine, 'blocks' => [['title' => 'X', 'exercises' => $many]]]))
            ->assertStatus(422)->assertJsonPath('message', 'Como máximo puedes tener 40 ejercicios por sección.');
        $this->assertSame('Pierna en casa', ProgramDayAssignment::with('workoutTemplate')->find($mine)->workoutTemplate->title);
    }
}
