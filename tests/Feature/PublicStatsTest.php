<?php

namespace Tests\Feature;

use App\Models\BlockedUser;
use App\Models\ClientExerciseLog;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Estadísticas públicas opt-in del perfil de otro usuario (app, Comunidad):
 * apagadas por defecto, solo agregados, sin contar sesiones finalizadas sin
 * ninguna serie registrada (caso Ayoub) y sin saltarse un bloqueo entre usuarios.
 */
class PublicStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
        // Jueves 2026-09-24: la semana ISO actual empieza el lunes 21.
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00'));

        $this->viewer = $this->makeUser();
        $this->owner = $this->makeUser();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(): User
    {
        $u = User::create([
            'first_name' => 'Test', 'last_name' => 'User', 'username' => 'u_'.uniqid(),
            'email' => uniqid().'@example.test', 'password' => bcrypt('p'),
            'user_type' => 'user', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $u->assignRole('user');

        return $u;
    }

    private function optIn(User $u): void
    {
        $u->forceFill(['show_public_stats' => true])->save();
    }

    private function statsFor(User $target)
    {
        return $this->getJson('/api/v1/user-public-stats?user_id='.$target->id);
    }

    public function test_hidden_by_default(): void
    {
        Sanctum::actingAs($this->viewer);

        $this->statsFor($this->owner)
            ->assertOk()
            ->assertJsonPath('data.visible', false)
            ->assertJsonMissingPath('data.stats');
    }

    public function test_owner_always_sees_own_stats(): void
    {
        Sanctum::actingAs($this->owner);

        $this->statsFor($this->owner)
            ->assertOk()
            ->assertJsonPath('data.visible', true)
            ->assertJsonStructure(['data' => ['stats' => ['workouts_last_30_days', 'records_count', 'avg_duration_minutes', 'week_streak']]]);
    }

    public function test_visible_to_others_only_after_opt_in(): void
    {
        $this->optIn($this->owner);
        Sanctum::actingAs($this->viewer);

        $this->statsFor($this->owner)
            ->assertOk()
            ->assertJsonPath('data.visible', true)
            ->assertJsonPath('data.stats.workouts_last_30_days', 0)
            ->assertJsonPath('data.stats.week_streak', 0)
            ->assertJsonPath('data.stats.avg_duration_minutes', null);
    }

    public function test_a_block_in_either_direction_hides_stats_even_with_opt_in(): void
    {
        $this->optIn($this->owner);
        Sanctum::actingAs($this->viewer);

        BlockedUser::create(['blocker_id' => $this->viewer->id, 'blocked_id' => $this->owner->id]);
        $this->statsFor($this->owner)->assertJsonPath('data.visible', false);

        BlockedUser::query()->delete();
        BlockedUser::create(['blocker_id' => $this->owner->id, 'blocked_id' => $this->viewer->id]);
        $this->statsFor($this->owner)->assertJsonPath('data.visible', false);
    }

    public function test_privacy_setting_roundtrip_and_default_off(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/my-privacy-settings')->assertOk()->assertJsonPath('data.show_public_stats', false);
        $this->postJson('/api/v1/my-privacy-settings', ['show_public_stats' => true])->assertOk()->assertJsonPath('data.show_public_stats', true);
        $this->getJson('/api/v1/my-privacy-settings')->assertJsonPath('data.show_public_stats', true);
        $this->postJson('/api/v1/my-privacy-settings', ['show_public_stats' => false])->assertJsonPath('data.show_public_stats', false);
        $this->postJson('/api/v1/my-privacy-settings', [])->assertStatus(422);
    }

    public function test_only_sessions_with_registered_sets_count_and_streak_and_average(): void
    {
        $this->optIn($this->owner);

        // Dos días de programa reales del owner (mismo camino que DeletedSessionDetailTest).
        Sanctum::actingAs($this->owner);
        $exercise = DB::table('exercises')->insertGetId(['title' => 'Press', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $assignmentIds = [];
        // La API solo deja crear entrenamientos desde la semana en curso; la fecha de la reseña se fija aparte.
        foreach (['2026-09-23', '2026-09-22'] as $date) {
            $res = $this->postJson('/api/v1/my-custom-workouts', [
                'title' => 'Sesión '.$date, 'date' => $date,
                'blocks' => [['title' => 'Principal', 'exercises' => [['exercise_id' => $exercise]]]],
            ])->assertOk();
            $assignmentIds[$date] = $res->json('data.assignments.0.assignment_id');
        }

        // Día 23 (semana actual): con series -> cuenta. Día 22: finalizada SIN ninguna serie -> no cuenta.
        ClientExerciseLog::create([
            'client_id' => $this->owner->id, 'exercise_id' => $exercise,
            'program_day_assignment_id' => $assignmentIds['2026-09-23'], 'performed_date' => '2026-09-23',
            'logged_sets' => [['reps' => 10, 'carga' => 50]],
        ]);
        WorkoutSessionReview::create(['user_id' => $this->owner->id, 'program_day_assignment_id' => $assignmentIds['2026-09-23'], 'duration_seconds' => 3600, 'volume_kg' => 500, 'completed_at' => '2026-09-23 19:00:00']);
        WorkoutSessionReview::create(['user_id' => $this->owner->id, 'program_day_assignment_id' => $assignmentIds['2026-09-22'], 'duration_seconds' => 4800, 'volume_kg' => 0, 'completed_at' => '2026-09-09 19:00:00']);
        // Workout suelto (sin asignación) con volumen, semana anterior: cuenta.
        WorkoutSessionReview::create(['user_id' => $this->owner->id, 'duration_seconds' => 1800, 'volume_kg' => 300, 'completed_at' => '2026-09-16 19:00:00']);
        // Workout suelto SIN volumen: no cuenta.
        WorkoutSessionReview::create(['user_id' => $this->owner->id, 'duration_seconds' => 7200, 'volume_kg' => 0, 'completed_at' => '2026-09-22 19:00:00']);

        Sanctum::actingAs($this->viewer);
        $this->statsFor($this->owner)
            ->assertOk()
            ->assertJsonPath('data.stats.workouts_last_30_days', 2)
            ->assertJsonPath('data.stats.avg_duration_minutes', 45)   // (3600 + 1800) / 2 s = 45 min
            ->assertJsonPath('data.stats.week_streak', 2);            // semana del 21/09 y semana del 14/09
    }
}
