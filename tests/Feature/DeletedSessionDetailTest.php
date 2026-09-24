<?php

namespace Tests\Feature;

use App\Models\ProgramDayAssignment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Si el coach borra desde el panel un entrenamiento que el cliente tiene en
 * curso (minimizado), la app vuelve a pedir el detalle del día al abrir la
 * sesión. Debe recibir un 404 ("ya no existe") -- la app descarta entonces
 * la sesión guardada en vez de dejar al cliente bloqueado. Antes, con la
 * plantilla borrada, el endpoint reventaba con un 500 que la app trataba
 * como un fallo de red.
 */
class DeletedSessionDetailTest extends TestCase
{
    use RefreshDatabase;

    private int $assignmentId;
    private int $templateId;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00'));

        $client = User::create([
            'first_name' => 'Test', 'last_name' => 'User', 'username' => 'u_'.uniqid(),
            'email' => uniqid().'@example.test', 'password' => bcrypt('p'),
            'user_type' => 'user', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $client->assignRole('user');
        Sanctum::actingAs($client);

        $exercise = DB::table('exercises')->insertGetId(['title' => 'Press', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $res = $this->postJson('/api/v1/my-custom-workouts', [
            'title' => 'Sesión', 'date' => '2026-09-24',
            'blocks' => [['title' => 'Principal', 'exercises' => [['exercise_id' => $exercise]]]],
        ])->assertOk();
        $this->assignmentId = $res->json('data.assignments.0.assignment_id');
        $this->templateId = $res->json('data.assignments.0.workout_template_id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function detail()
    {
        return $this->getJson('/api/v1/my-calendar-day-detail?program_day_assignment_id='.$this->assignmentId);
    }

    public function test_detail_works_while_it_exists(): void
    {
        $this->detail()->assertOk();
    }

    public function test_coach_removing_the_day_returns_404(): void
    {
        // Mismo borrado que ClientProfileCalendarController::removeAssignment (soft delete).
        ProgramDayAssignment::where('id', $this->assignmentId)->delete();
        $this->detail()->assertStatus(404);
    }

    public function test_coach_deleting_the_template_returns_404_not_500(): void
    {
        WorkoutTemplate::find($this->templateId)->delete(); // soft delete
        $this->detail()->assertStatus(404)->assertJsonPath('message', 'Este entrenamiento ya no existe.');
    }
}
