<?php

namespace Tests\Feature;

use App\Models\ClientExerciseLog;
use App\Models\PersonalRecord;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Técnicas de "total de repeticiones" (rest-pause, drop sets...): antes se
 * apuntaban como una serie con todas las reps al peso inicial y el 1RM
 * salía inflado. Ahora la serie lleva `tecnica` y, desde la app nueva,
 * `partes` (tramos extra) -- ver LoggedSetMath.
 */
class TechniqueSetRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private int $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
        Carbon::setTestNow(Carbon::parse('2026-10-03 10:00:00'));

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

    /** Día de calendario con un ejercicio cuya técnica es $tecnica; devuelve [assignmentId, wteId]. */
    private function dayWithTechnique(?string $tecnica, string $series = 'todas'): array
    {
        $res = $this->postJson('/api/v1/my-custom-workouts', [
            'title'  => 'Sesión',
            'date'   => '2026-10-03',
            'blocks' => [['title' => 'Principal', 'exercises' => [['exercise_id' => $this->exercise]]]],
        ])->assertOk();

        $assignmentId = $res->json('data.assignments.0.assignment_id');
        $wteId = DB::table('workout_template_exercises')
            ->join('workout_template_blocks', 'workout_template_blocks.id', '=', 'workout_template_exercises.workout_template_block_id')
            ->where('workout_template_blocks.workout_template_id', $res->json('data.assignments.0.workout_template_id'))
            ->value('workout_template_exercises.id');

        $prescribed = json_decode((string) DB::table('workout_template_exercises')->where('id', $wteId)->value('prescribed'), true) ?: [];
        if ($tecnica) {
            $prescribed['tecnica'] = $tecnica;
            $prescribed['tecnica_series'] = $series;
        }
        DB::table('workout_template_exercises')->where('id', $wteId)->update([
            'prescribed'      => json_encode($prescribed),
            'enabled_metrics' => json_encode(['reps', 'carga', 'rir']),
        ]);

        return [$assignmentId, $wteId];
    }

    private function log(int $assignmentId, int $wteId, array $sets)
    {
        return $this->postJson('/api/v1/my-calendar-log-sets', [
            'workout_template_exercise_id' => $wteId,
            'program_day_assignment_id'    => $assignmentId,
            'logged_sets'                  => $sets,
        ])->assertOk();
    }

    private function best(string $type): ?float
    {
        $v = PersonalRecord::where('user_id', $this->client->id)->where('exercise_id', $this->exercise)->where('record_type', $type)->max('value');

        return $v !== null ? (float) $v : null;
    }

    public function test_old_app_total_reps_set_is_tagged_and_does_not_inflate_1rm(): void
    {
        [$a, $w] = $this->dayWithTechnique('rest_pause', 'ultima');

        // Serie normal 80×8 y, en la última, rest-pause apuntado como 80×13.
        $this->log($a, $w, [['carga' => 80, 'reps' => 8, 'rir' => 2], ['carga' => 80, 'reps' => 13, 'rir' => 0]]);

        $sets = ClientExerciseLog::latest('id')->first()->logged_sets;
        $this->assertArrayNotHasKey('tecnica', $sets[0]);
        $this->assertSame('rest_pause', $sets[1]['tecnica']);

        $this->assertEqualsWithDelta(101.33, $this->best('max_1rm'), 0.01); // 80 × (1 + 8/30), no 80 × 13
        $this->assertSame(80.0, $this->best('max_weight'));
    }

    public function test_new_app_parts_count_for_volume_but_1rm_uses_first_segment(): void
    {
        [$a, $w] = $this->dayWithTechnique('drop_sets');

        $this->log($a, $w, [[
            'carga' => 100, 'reps' => 6, 'rir' => 0, 'tecnica' => 'drop_sets',
            'partes' => [['carga' => 80, 'reps' => 5], ['carga' => 60, 'reps' => 6]],
        ]]);

        $set = ClientExerciseLog::latest('id')->first()->logged_sets[0];
        $this->assertCount(2, $set['partes']);
        $this->assertEqualsWithDelta(120.0, $this->best('max_1rm'), 0.01); // 100 × (1 + 6/30)
        $this->assertEqualsWithDelta(600 + 400 + 360, $this->best('max_volume'), 0.01);
    }

    public function test_parts_are_sanitized(): void
    {
        [$a, $w] = $this->dayWithTechnique(null);

        $this->log($a, $w, [[
            'carga' => 50, 'reps' => 10, 'rir' => 1, 'tecnica' => 'inventada',
            'partes' => array_fill(0, 9, ['carga' => 'x', 'reps' => 3]) + [8 => ['carga' => 40, 'reps' => 0]],
            'otra_clave' => 'fuera',
        ]]);

        $set = ClientExerciseLog::latest('id')->first()->logged_sets[0];
        $this->assertArrayNotHasKey('tecnica', $set);
        $this->assertArrayNotHasKey('otra_clave', $set);
        $this->assertCount(6, $set['partes']);
        $this->assertNull($set['partes'][0]['carga']);
    }

    public function test_recalculate_command_removes_inflated_1rm_records(): void
    {
        [$a, $w] = $this->dayWithTechnique('rest_pause');

        // Registro antiguo creado sin `tecnica` (antes de este cambio): el
        // observer de entonces lo contaba como 80×13.
        ClientExerciseLog::withoutEvents(fn () => ClientExerciseLog::create([
            'client_id' => $this->client->id, 'workout_template_exercise_id' => $w, 'exercise_id' => $this->exercise,
            'program_day_assignment_id' => $a, 'performed_date' => '2026-09-20',
            'logged_sets' => [['carga' => 80, 'reps' => 13, 'rir' => 0]],
        ]));
        PersonalRecord::create(['user_id' => $this->client->id, 'exercise_id' => $this->exercise, 'record_type' => 'max_1rm', 'value' => 114.67, 'achieved_at' => '2026-09-20']);
        // Serie normal real posterior.
        ClientExerciseLog::withoutEvents(fn () => ClientExerciseLog::create([
            'client_id' => $this->client->id, 'exercise_id' => $this->exercise, 'performed_date' => '2026-09-27',
            'logged_sets' => [['carga' => 85, 'reps' => 5, 'rir' => 1]],
        ]));

        $this->artisan('records:recalcular-tecnicas')->assertSuccessful();
        $this->assertEqualsWithDelta(114.67, $this->best('max_1rm'), 0.01); // dry run: no cambia

        $this->artisan('records:recalcular-tecnicas', ['--apply' => true])->assertSuccessful();
        $this->assertEqualsWithDelta(99.17, $this->best('max_1rm'), 0.01); // 85 × (1 + 5/30)
    }
}
