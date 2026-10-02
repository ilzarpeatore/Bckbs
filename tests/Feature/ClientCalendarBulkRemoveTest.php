<?php

namespace Tests\Feature;

use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST admin/client-calendar-bulk-remove — vaciar en bloque el calendario de
 * un cliente desde el panel (selección múltiple, "Vaciar semana", "Vaciar
 * calendario"). Lo que se verifica aquí es justo lo que no se puede romper:
 *  - nunca se borra una sesión YA REALIZADA (ni por su assignment_id, ni por
 *    caer en un día que el cliente cerró sin enlace a la asignación);
 *  - el alcance `week` se queda dentro de sus fechas;
 *  - el alcance `all` barre todo el calendario, también los meses que el
 *    panel no tiene cargados;
 *  - un día de un programa de biblioteca COMPARTIDO con otro cliente se
 *    cuenta como bloqueado y se deja intacto (TemplateIsolationGuard): el
 *    aislamiento entre clientes manda sobre el vaciado.
 */
class ClientCalendarBulkRemoveTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;
    private User $client;
    private TrainingProgram $program;
    /** @var array<string,ProgramDayAssignment> */
    private array $days = [];

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');

        $this->coach = $this->makeUser('coach', 'coach');
        $this->coach->assignRole('admin');
        $this->client = $this->makeUser('client', 'user');

        // Copia propia del cliente (client_id) -> owner "client:<id>", vaciable.
        $this->program = TrainingProgram::create([
            'title'        => 'Mesociclo 1',
            'coach_id'     => $this->coach->id,
            'client_id'    => $this->client->id,
            'num_weeks'    => 4,
            'fecha_inicio' => '2026-10-05',
            'activo'       => true,
        ]);
        ProgramClientAssignment::create([
            'training_program_id' => $this->program->id,
            'client_id'           => $this->client->id,
            'start_date'          => '2026-10-05', // lunes
            'fecha_fin'           => '2026-11-01',
            'activo'              => true,
        ]);

        // Semana 1: lunes 05-oct y miércoles 07-oct. Semana 2: lunes 12 y martes 13.
        $this->days = [
            'w1d1' => $this->makeDay($this->program, 1, 1),
            'w1d3' => $this->makeDay($this->program, 1, 3),
            'w2d1' => $this->makeDay($this->program, 2, 1),
            'w2d2' => $this->makeDay($this->program, 2, 2),
        ];

        Sanctum::actingAs($this->coach, ['*']);
    }

    private function makeUser(string $prefix, string $type): User
    {
        return User::create([
            'first_name' => ucfirst($prefix),
            'last_name'  => 'Test',
            'username'   => $prefix.'_'.uniqid(),
            'email'      => uniqid().'@example.test',
            'password'   => bcrypt('password'),
            'user_type'  => $type,
            'status'     => 'active',
            'login_type' => 'manual',
        ]);
    }

    private function makeDay(TrainingProgram $program, int $week, int $dow): ProgramDayAssignment
    {
        $template = WorkoutTemplate::create(['coach_id' => $this->coach->id, 'title' => 'Sesion '.$week.'-'.$dow]);

        return ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'         => $week,
            'day_of_week'         => $dow,
            'workout_template_id' => $template->id,
        ]);
    }

    private function bulk(array $payload)
    {
        return $this->postJson('/api/admin/client-calendar-bulk-remove', array_merge(
            ['client_id' => $this->client->id],
            $payload
        ));
    }

    private function alive(string $key): bool
    {
        return ProgramDayAssignment::whereKey($this->days[$key]->id)->exists();
    }

    public function test_selection_removes_only_the_marked_and_unfinished_sessions(): void
    {
        WorkoutSessionReview::create([
            'user_id'                   => $this->client->id,
            'program_day_assignment_id' => $this->days['w1d1']->id,
            'completed_at'              => '2026-10-05 19:00:00',
        ]);

        $res = $this->bulk([
            'scope'          => 'selection',
            'assignment_ids' => [$this->days['w1d1']->id, $this->days['w1d3']->id],
        ])->assertStatus(200);

        $this->assertSame(1, $res->json('data.deleted'));
        $this->assertSame(1, $res->json('data.skipped_completed'));
        $this->assertTrue($this->alive('w1d1'), 'la sesion realizada no se borra');
        $this->assertFalse($this->alive('w1d3'));
        $this->assertTrue($this->alive('w2d1'), 'no seleccionada, intacta');
    }

    public function test_a_session_of_another_client_is_never_removed_by_id(): void
    {
        $other = $this->makeUser('other', 'user');
        $otherProgram = TrainingProgram::create([
            'title' => 'Otro', 'coach_id' => $this->coach->id, 'client_id' => $other->id,
            'num_weeks' => 4, 'fecha_inicio' => '2026-10-05', 'activo' => true,
        ]);
        $otherDay = $this->makeDay($otherProgram, 1, 1);

        $res = $this->bulk(['scope' => 'selection', 'assignment_ids' => [$otherDay->id]])->assertStatus(200);

        $this->assertSame(0, $res->json('data.deleted'));
        $this->assertSame(1, $res->json('data.not_found'));
        $this->assertTrue(ProgramDayAssignment::whereKey($otherDay->id)->exists());
    }

    public function test_week_scope_stays_inside_its_dates(): void
    {
        $res = $this->bulk(['scope' => 'week', 'from' => '2026-10-05', 'to' => '2026-10-11'])->assertStatus(200);

        $this->assertSame(2, $res->json('data.deleted'));
        $this->assertFalse($this->alive('w1d1'));
        $this->assertFalse($this->alive('w1d3'));
        $this->assertTrue($this->alive('w2d1'));
        $this->assertTrue($this->alive('w2d2'));
    }

    public function test_week_scope_keeps_a_day_the_client_already_closed_without_assignment_link(): void
    {
        // Sesión finalizada sin program_day_assignment_id (workout suelto): el
        // día 07-oct queda "realizado" y no se vacía, igual que el panel lo
        // pinta en verde por fecha.
        WorkoutSessionReview::create([
            'user_id'                   => $this->client->id,
            'program_day_assignment_id' => null,
            'completed_at'              => '2026-10-07 08:30:00',
        ]);

        $res = $this->bulk(['scope' => 'week', 'from' => '2026-10-05', 'to' => '2026-10-11'])->assertStatus(200);

        $this->assertSame(1, $res->json('data.deleted'));
        $this->assertSame(1, $res->json('data.skipped_completed'));
        $this->assertFalse($this->alive('w1d1'));
        $this->assertTrue($this->alive('w1d3'), 'el dia 07-oct estaba realizado');
    }

    public function test_all_scope_sweeps_every_month_and_respects_what_was_done(): void
    {
        WorkoutSessionReview::create([
            'user_id'                   => $this->client->id,
            'program_day_assignment_id' => $this->days['w2d2']->id,
            'completed_at'              => '2026-10-13 20:00:00',
        ]);

        $res = $this->bulk(['scope' => 'all'])->assertStatus(200);

        $this->assertSame(3, $res->json('data.deleted'));
        $this->assertSame(1, $res->json('data.skipped_completed'));
        $this->assertFalse($this->alive('w1d1'));
        $this->assertFalse($this->alive('w1d3'));
        $this->assertFalse($this->alive('w2d1'));
        $this->assertTrue($this->alive('w2d2'));
    }

    public function test_all_scope_does_not_touch_a_library_program_shared_with_another_client(): void
    {
        $other = $this->makeUser('other', 'user');
        $library = TrainingProgram::create([
            'title' => 'Plantilla biblioteca', 'coach_id' => $this->coach->id,
            'num_weeks' => 4, 'fecha_inicio' => '2026-10-05', 'activo' => true,
        ]);
        $sharedDay = $this->makeDay($library, 1, 5);

        // Datos LEGACY a propósito: hoy ProgramClientAssignment ya impide
        // asignar un programa de biblioteca directo a un cliente (hay que
        // pasar por assignToClient(), que crea su copia), así que las filas se
        // insertan a pelo para reproducir lo que quedó en producción antes de
        // esa regla. Es justo el caso que el guard tiene que seguir frenando.
        foreach ([$this->client->id, $other->id] as $clientId) {
            DB::table('program_client_assignments')->insert([
                'training_program_id' => $library->id,
                'client_id'           => $clientId,
                'start_date'          => '2026-10-05',
                'fecha_fin'           => '2026-11-01',
                'activo'              => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        $res = $this->bulk(['scope' => 'all'])->assertStatus(200);

        $this->assertSame(4, $res->json('data.deleted'), 'los 4 dias de su copia propia');
        $this->assertSame(1, $res->json('data.skipped_blocked'));
        $this->assertTrue(ProgramDayAssignment::whereKey($sharedDay->id)->exists());
    }

    public function test_selection_requires_assignment_ids(): void
    {
        $this->bulk(['scope' => 'selection'])->assertStatus(422);
    }
}
