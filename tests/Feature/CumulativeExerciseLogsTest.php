<?php

namespace Tests\Feature;

use App\Models\ClientExerciseLog;
use App\Models\Role;
use App\Models\User;
use App\Services\MuscleVolumeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La app manda en cada serie marcada TODAS las series ya completadas del
 * ejercicio, y logSets() crea una fila nueva cada vez (filas "acumuladas").
 * Antes, 3 series reales contaban como 6 en volumen/series/rankings.
 * Ver ClientExerciseLog::scopeLatestSnapshots().
 */
class CumulativeExerciseLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private int $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00'));

        $this->client = User::create([
            'first_name' => 'Test', 'last_name' => 'User', 'username' => 'u_'.uniqid(),
            'email' => uniqid().'@example.test', 'password' => bcrypt('p'),
            'user_type' => 'user', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $this->client->assignRole('user');
        $this->exercise = DB::table('exercises')->insertGetId(['title' => 'Press banca', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($this->client);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Crea un día en el calendario personal (vía el endpoint real) y devuelve su program_day_assignment_id. */
    private function assignmentFor(string $date, array $exerciseIds): array
    {
        $res = $this->postJson('/api/v1/my-custom-workouts', [
            'title'  => 'Sesión '.$date,
            'date'   => $date,
            'blocks' => [['title' => 'Principal', 'exercises' => array_map(fn ($id) => ['exercise_id' => $id], $exerciseIds)]],
        ])->assertOk();

        $assignmentId = $res->json('data.assignments.0.assignment_id');
        $wteIds = DB::table('workout_template_exercises')
            ->join('workout_template_blocks', 'workout_template_blocks.id', '=', 'workout_template_exercises.workout_template_block_id')
            ->where('workout_template_blocks.workout_template_id', $res->json('data.assignments.0.workout_template_id'))
            ->orderBy('workout_template_exercises.sequence')
            ->pluck('workout_template_exercises.id')
            ->all();

        return [$assignmentId, $wteIds];
    }

    /** Igual que la app: una petición por serie marcada, con todas las completadas hasta ese momento. */
    private function tickSetsOneByOne(int $assignmentId, int $wteId, int $count, float $carga = 50, int $reps = 10): void
    {
        for ($n = 1; $n <= $count; $n++) {
            $this->postJson('/api/v1/my-calendar-log-sets', [
                'workout_template_exercise_id' => $wteId,
                'program_day_assignment_id'    => $assignmentId,
                'logged_sets'                  => array_fill(0, $n, ['reps' => $reps, 'carga' => $carga, 'rir' => 2]),
            ])->assertOk();
        }
    }

    // endDate = mañana: en sqlite performed_date se guarda como
    // "Y-m-d 00:00:00" y un "<= hoy" en texto lo excluiría (en MySQL es DATE).
    private function volume(): array
    {
        return MuscleVolumeService::computeForClient($this->client->id, 7, true, Carbon::tomorrow()->toDateString());
    }

    public function test_sets_ticked_one_by_one_count_once(): void
    {
        [$pda, [$wte]] = $this->assignmentFor('2026-09-24', [$this->exercise]);
        $this->tickSetsOneByOne($pda, $wte, 3);

        $this->assertSame(3, ClientExerciseLog::count()); // siguen guardándose 3 fotos...
        $v = $this->volume();
        $this->assertSame(3, $v['totalSeries']);           // ...pero cuentan como 3 series, no 6
        $this->assertEquals(1500, $v['totalVolume']);       // 3 x 10 reps x 50 kg, no 3000

        $top = $this->getJson('/api/v1/my-top-exercises?days=7&end_date='.Carbon::tomorrow()->toDateString())->assertOk();
        $this->assertSame(1, $top->json('data.0.sessions'));
        $this->assertSame(3, $top->json('data.0.sets'));
    }

    public function test_unticking_a_set_uses_the_final_state(): void
    {
        [$pda, [$wte]] = $this->assignmentFor('2026-09-24', [$this->exercise]);
        $this->tickSetsOneByOne($pda, $wte, 3);
        // Desmarca la 3ª: la app vuelve a mandar solo 2.
        $this->postJson('/api/v1/my-calendar-log-sets', [
            'workout_template_exercise_id' => $wte, 'program_day_assignment_id' => $pda,
            'logged_sets' => array_fill(0, 2, ['reps' => 10, 'carga' => 50, 'rir' => 2]),
        ])->assertOk();

        $this->assertSame(2, $this->volume()['totalSeries']);
    }

    public function test_different_sessions_and_repeated_exercise_are_kept_apart(): void
    {
        // Dos sesiones distintas del mismo ejercicio, y en la 2ª el mismo
        // ejercicio aparece dos veces en la plantilla (dos filas propias).
        [$pdaA, [$wteA]] = $this->assignmentFor('2026-09-22', [$this->exercise]);
        [$pdaB, [$wteB1, $wteB2]] = $this->assignmentFor('2026-09-24', [$this->exercise, $this->exercise]);

        $this->tickSetsOneByOne($pdaA, $wteA, 3);
        $this->tickSetsOneByOne($pdaB, $wteB1, 2);
        $this->tickSetsOneByOne($pdaB, $wteB2, 4);

        $this->assertSame(3 + 2 + 4, $this->volume()['totalSeries']);

        $top = $this->getJson('/api/v1/my-top-exercises?days=7&end_date='.Carbon::tomorrow()->toDateString())->assertOk();
        $this->assertSame(9, $top->json('data.0.sets'));
    }

    public function test_unticking_all_sets_counts_as_zero(): void
    {
        [$pda, [$wte]] = $this->assignmentFor('2026-09-24', [$this->exercise]);
        $this->tickSetsOneByOne($pda, $wte, 3);
        $this->assertSame(3, $this->volume()['totalSeries']);

        // Desmarca las 3: la app manda la lista vacía (antes -> 422 y la
        // foto de 3 series seguía contando).
        $this->postJson('/api/v1/my-calendar-log-sets', [
            'workout_template_exercise_id' => $wte, 'program_day_assignment_id' => $pda,
            'logged_sets' => [],
        ])->assertOk();

        $last = ClientExerciseLog::orderByDesc('id')->first();
        $this->assertSame([], $last->logged_sets);
        $this->assertSame(0, $this->volume()['totalSeries']);

        $top = $this->getJson('/api/v1/my-top-exercises?days=7&end_date='.Carbon::tomorrow()->toDateString())->assertOk();
        $top->assertJsonCount(0, 'data'); // ni sesión ni series

        // Y una sesión vacía no se ofrece como "la última vez" del ejercicio.
        $detail = $this->getJson('/api/v1/my-calendar-day-detail?program_day_assignment_id='.$pda)->assertOk();
        $this->assertNull($detail->json('data.blocks.0.exercises.0.last_performance'));

        // Sin el campo sigue siendo un error de validación.
        $this->postJson('/api/v1/my-calendar-log-sets', [
            'workout_template_exercise_id' => $wte, 'program_day_assignment_id' => $pda,
        ])->assertStatus(422);
    }

    /** Serie suelta (sin día de calendario), como el entrenamiento libre de la app. */
    private function logStandalone(?string $sessionKey, int $sets): void
    {
        $payload = [
            'exercise_id' => $this->exercise,
            'logged_sets' => array_fill(0, $sets, ['reps' => 10, 'carga' => 50, 'rir' => 2]),
        ];
        if ($sessionKey !== null) {
            $payload['session_key'] = $sessionKey;
        }
        $this->postJson('/api/v1/my-calendar-log-sets', $payload)->assertOk();
    }

    public function test_session_key_separates_standalone_sessions_on_the_same_day(): void
    {
        // Mañana: 3 series marcadas una a una en la sesión A.
        foreach ([1, 2, 3] as $n) $this->logStandalone('sess-A:2026-09-24', $n);
        // Tarde: 2 series en la sesión B, mismo ejercicio, mismo día.
        foreach ([1, 2] as $n) $this->logStandalone('sess-B:2026-09-24', $n);

        $this->assertSame('sess-A:2026-09-24', ClientExerciseLog::orderBy('id')->first()->session_key);
        // 3 + 2, no solo la última foto del día (2) ni la suma de fotos (9).
        $this->assertSame(5, $this->volume()['totalSeries']);

        $top = $this->getJson('/api/v1/my-top-exercises?days=7&end_date='.Carbon::tomorrow()->toDateString())->assertOk();
        $this->assertSame(2, $top->json('data.0.sessions'));
        $this->assertSame(5, $top->json('data.0.sets'));
    }

    public function test_same_session_key_collapses_snapshots_and_old_rows_keep_day_grouping(): void
    {
        foreach ([1, 2, 3, 4] as $n) $this->logStandalone('sess-A', $n);
        $this->assertSame(4, $this->volume()['totalSeries']);

        // Filas sin session_key (versión vieja de la app): se agrupan por
        // día como siempre, aparte de la sesión con clave.
        foreach ([1, 2] as $n) $this->logStandalone(null, $n);
        $this->assertSame(4 + 2, $this->volume()['totalSeries']);
    }

    public function test_session_key_is_validated(): void
    {
        $this->postJson('/api/v1/my-calendar-log-sets', [
            'exercise_id' => $this->exercise, 'logged_sets' => [], 'session_key' => 'con espacios',
        ])->assertStatus(422);
        $this->postJson('/api/v1/my-calendar-log-sets', [
            'exercise_id' => $this->exercise, 'logged_sets' => [], 'session_key' => str_repeat('a', 65),
        ])->assertStatus(422);
    }
}
