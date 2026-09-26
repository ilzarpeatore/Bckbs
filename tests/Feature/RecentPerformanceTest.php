<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `recent_performance` (2026-09-26): historial reciente por ejercicio (una
 * entrada por sesión, la más nueva primero, máx. 6) que la app usa para
 * precargar la carga con "la última que usó dentro del rango de reps que le
 * toca". `last_performance` solo trae la última sesión y no basta si esa fue
 * con otro rango de reps.
 */
class RecentPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private int $exercise;
    private int $otherExercise;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');

        $this->client = User::create([
            'first_name' => 'Test', 'last_name' => 'User', 'username' => 'u_'.uniqid(),
            'email' => uniqid().'@example.test', 'password' => bcrypt('p'),
            'user_type' => 'user', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $this->client->assignRole('user');
        $this->exercise = DB::table('exercises')->insertGetId(['title' => 'Jalón', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->otherExercise = DB::table('exercises')->insertGetId(['title' => 'Curl', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($this->client);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Día de calendario propio con los ejercicios dados; devuelve [assignmentId, [wteIds...]]. */
    private function assignmentFor(string $date, array $exerciseIds): array
    {
        $res = $this->postJson('/api/v1/my-custom-workouts', [
            'title'  => 'Sesión '.$date,
            'date'   => $date,
            'blocks' => [['title' => 'Principal', 'exercises' => array_map(fn ($id) => ['exercise_id' => $id], $exerciseIds)]],
        ])->assertOk();

        $wteIds = DB::table('workout_template_exercises')
            ->join('workout_template_blocks', 'workout_template_blocks.id', '=', 'workout_template_exercises.workout_template_block_id')
            ->where('workout_template_blocks.workout_template_id', $res->json('data.assignments.0.workout_template_id'))
            ->orderBy('workout_template_exercises.sequence')
            ->pluck('workout_template_exercises.id')
            ->all();

        return [$res->json('data.assignments.0.assignment_id'), $wteIds];
    }

    private function logSession(string $date, array $sets): void
    {
        Carbon::setTestNow(Carbon::parse($date.' 10:00:00'));
        [$pda, [$wte]] = $this->assignmentFor($date, [$this->exercise]);
        $this->postJson('/api/v1/my-calendar-log-sets', [
            'workout_template_exercise_id' => $wte,
            'program_day_assignment_id'    => $pda,
            'logged_sets'                  => $sets,
        ])->assertOk();
    }

    private function exercisesOfDay(int $assignmentId): array
    {
        return $this->getJson('/api/v1/my-calendar-day-detail?program_day_assignment_id='.$assignmentId)
            ->assertOk()
            ->json('data.blocks.0.exercises');
    }

    public function test_devuelve_las_ultimas_6_sesiones_la_mas_nueva_primero(): void
    {
        foreach (range(1, 8) as $day) {
            $this->logSession(sprintf('2026-09-%02d', $day), [['reps' => 10 + $day, 'carga' => 40 + $day, 'rir' => 2]]);
        }

        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));
        [$pda] = $this->assignmentFor('2026-09-20', [$this->exercise, $this->otherExercise]);
        $exercises = collect($this->exercisesOfDay($pda))->keyBy('exercise_id');

        $recent = $exercises[$this->exercise]['recent_performance'];
        $this->assertCount(6, $recent);
        $this->assertSame('2026-09-08', $recent[0]['date']);
        $this->assertSame(48, $recent[0]['sets'][0]['carga']);
        $this->assertSame('2026-09-03', $recent[5]['date']);
        // last_performance sigue siendo la última sesión (compatibilidad).
        $this->assertSame(48, $exercises[$this->exercise]['last_performance']['sets'][0]['carga']);
        // Un ejercicio que nunca hizo no trae historial.
        $this->assertNull($exercises[$this->otherExercise]['recent_performance']);
    }

    public function test_una_sesion_con_todas_las_series_desmarcadas_no_cuenta(): void
    {
        $this->logSession('2026-09-10', [['reps' => 12, 'carga' => 40, 'rir' => 2]]);
        $this->logSession('2026-09-12', []); // desmarcó todas: no se hizo

        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));
        [$pda] = $this->assignmentFor('2026-09-20', [$this->exercise]);
        $recent = $this->exercisesOfDay($pda)[0]['recent_performance'];

        $this->assertCount(1, $recent);
        $this->assertSame('2026-09-10', $recent[0]['date']);
    }

    public function test_no_mezcla_el_historial_de_otro_cliente(): void
    {
        $this->logSession('2026-09-10', [['reps' => 12, 'carga' => 40, 'rir' => 2]]);

        $other = User::create([
            'first_name' => 'Otro', 'last_name' => 'User', 'username' => 'o_'.uniqid(),
            'email' => uniqid().'@example.test', 'password' => bcrypt('p'),
            'user_type' => 'user', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $other->assignRole('user');
        Sanctum::actingAs($other);

        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));
        [$pda] = $this->assignmentFor('2026-09-20', [$this->exercise]);

        $this->assertNull($this->exercisesOfDay($pda)[0]['recent_performance']);
    }
}
