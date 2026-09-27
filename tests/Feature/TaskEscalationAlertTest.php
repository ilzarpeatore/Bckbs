<?php

namespace Tests\Feature;

use App\Enums\ExceptionCategory;
use App\Models\CoachExceptionItem;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskEscalationAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * docs/TAREAS_PENDIENTES.md de AgenticdesignBS, ítem 2.21: hasta ahora una
 * tarea priority=high creada por el Agente de Soporte/Onboarding (POST
 * task-store) no avisaba activamente al coach -- se quedaba en el panel
 * hasta que la abría por su cuenta. Cubre que el aviso se dispare solo para
 * priority=high con client_id, que sea idempotente por tarea, y que no se
 * dispare para prioridades normales ni tareas sin cliente.
 */
class TaskEscalationAlertTest extends TestCase
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

    public function test_high_priority_task_with_client_creates_exception_item(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);

        Sanctum::actingAs($coach, ['*']);
        $response = $this->postJson('/api/admin/task-store', [
            'title'     => 'Mención de dolor lumbar',
            'client_id' => $client->id,
            'priority'  => 'high',
        ]);

        $response->assertStatus(201);
        $taskId = $response->json('data.id');

        $this->assertDatabaseHas('coach_exception_items', [
            'coach_id'    => $coach->id,
            'client_id'   => $client->id,
            'category'    => ExceptionCategory::TAREA_ESCALADA_AGENTE->value,
            'source_type' => Task::class,
            'source_id'   => $taskId,
        ]);
    }

    public function test_medium_priority_task_does_not_create_exception_item(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);

        Sanctum::actingAs($coach, ['*']);
        $response = $this->postJson('/api/admin/task-store', [
            'title'     => 'Revisar plan la semana que viene',
            'client_id' => $client->id,
            'priority'  => 'medium',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('coach_exception_items', 0);
    }

    public function test_high_priority_task_without_client_does_not_create_exception_item(): void
    {
        $coach = $this->makeCoach();

        Sanctum::actingAs($coach, ['*']);
        $response = $this->postJson('/api/admin/task-store', [
            'title'    => 'Tarea administrativa sin cliente',
            'priority' => 'high',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('coach_exception_items', 0);
    }

    public function test_evaluate_is_idempotent_for_the_same_task(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);

        $task = Task::create([
            'type'      => 'management',
            'author_id' => $coach->id,
            'client_id' => $client->id,
            'title'     => 'Mención de precio/cancelación',
            'priority'  => 'high',
            'status'    => 'pending',
        ]);

        $service = new TaskEscalationAlertService();
        $first = $service->evaluate($task);
        $second = $service->evaluate($task);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertDatabaseCount('coach_exception_items', 1);
    }

    public function test_resolves_coach_via_client_coach_id(): void
    {
        $coach = $this->makeCoach();
        $otherCoach = $this->makeCoach();
        $client = $this->makeClient($coach->id);

        $task = Task::create([
            'type'      => 'management',
            'author_id' => $coach->id,
            'client_id' => $client->id,
            'title'     => 'Cliente sin ninguna sesión completada',
            'priority'  => 'high',
            'status'    => 'pending',
        ]);

        (new TaskEscalationAlertService())->evaluate($task);

        $this->assertDatabaseHas('coach_exception_items', [
            'client_id' => $client->id,
            'coach_id'  => $coach->id,
        ]);
        $this->assertDatabaseMissing('coach_exception_items', [
            'client_id' => $client->id,
            'coach_id'  => $otherCoach->id,
        ]);
    }
}
