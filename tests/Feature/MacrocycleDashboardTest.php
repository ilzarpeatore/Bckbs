<?php

namespace Tests\Feature;

use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\ProgramDayAssignment;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Página /macrociclos del panel: agrupación por macrociclo (título o manual),
 * datos planificados del dashboard y referencias MEV/MRV.
 */
class MacrocycleDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');

        $this->coach = $this->makeAdmin('coach_macro', 'coach-macro@example.test');
        Sanctum::actingAs($this->coach);
    }

    private function makeAdmin(string $username, string $email): User
    {
        $user = User::create([
            'first_name' => 'Coach',
            'last_name'  => 'Macro',
            'username'   => $username,
            'email'      => $email,
            'password'   => bcrypt('password'),
            'user_type'  => 'coach',
            'status'     => 'active',
            'login_type' => 'manual',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function program(string $title, array $weeks = [], ?int $coachId = null): TrainingProgram
    {
        $program = TrainingProgram::create([
            'title'       => $title,
            'coach_id'    => $coachId ?? $this->coach->id,
            'num_weeks'   => count($weeks),
            'is_personal' => false,
            'activo'      => true,
        ]);

        foreach ($weeks as $week => $exercises) {
            $template = WorkoutTemplate::create(['coach_id' => $program->coach_id, 'title' => "{$title} · Torso (S{$week})"]);
            $block = WorkoutTemplateBlock::create(['workout_template_id' => $template->id, 'title' => 'Principal', 'order' => 1]);
            foreach ($exercises as $i => [$exercise, $prescribed]) {
                WorkoutTemplateExercise::create([
                    'workout_template_block_id' => $block->id,
                    'exercise_id'               => $exercise->id,
                    'sequence'                  => $i + 1,
                    'prescribed'                => $prescribed,
                    'enabled_metrics'           => ['reps', 'carga', 'rir'],
                ]);
            }
            ProgramDayAssignment::create([
                'training_program_id' => $program->id,
                'week_number'         => $week,
                'day_of_week'         => 1,
                'workout_template_id' => $template->id,
                'is_deload'           => $week === 2,
            ]);
        }

        return $program;
    }

    private function exercise(string $title, string $bodyPart): Exercise
    {
        $bp = BodyPart::firstOrCreate(['title' => $bodyPart], ['status' => 'active']);

        return Exercise::create(['title' => $title, 'status' => 'active', 'bodypart_ids' => [$bp->id]]);
    }

    public function test_agrupa_por_titulo_y_la_asignacion_manual_manda(): void
    {
        $m1 = $this->program('Macrociclo Carlos - Mesociclo 1');
        $m2 = $this->program('Macrociclo Carlos - Mesociclo 2');
        $suelto = $this->program('Fuerza tren inferior');

        $res = $this->getJson('/api/admin/training-program-macrocycles')->assertOk();
        $groups = collect($res->json('data'));
        $this->assertCount(1, $groups);
        $this->assertSame('Macrociclo Carlos', $groups[0]['name']);
        $this->assertSame([1, 2], array_column($groups[0]['mesocycles'], 'mesocycle_number'));
        $this->assertSame([$suelto->id], array_column($res->json('unassigned'), 'id'));

        $this->postJson('/api/admin/training-program-set-macrocycle', [
            'id' => $suelto->id, 'macrocycle_name' => 'macrociclo carlos', 'mesocycle_number' => 3,
        ])->assertOk();

        $groups = collect($this->getJson('/api/admin/training-program-macrocycles')->json('data'));
        $this->assertCount(1, $groups);
        $mesos = $groups[0]['mesocycles'];
        $this->assertSame([$m1->id, $m2->id, $suelto->id], array_column($mesos, 'id'));
        $this->assertSame('manual', $mesos[2]['grouping']);

        // Quitar la asignación manual lo devuelve a suelto
        $this->postJson('/api/admin/training-program-set-macrocycle', ['id' => $suelto->id, 'macrocycle_name' => null])->assertOk();
        $res = $this->getJson('/api/admin/training-program-macrocycles');
        $this->assertSame([$suelto->id], array_column($res->json('unassigned'), 'id'));
    }

    public function test_no_se_puede_asignar_el_programa_de_otro_coach(): void
    {
        $other = $this->makeAdmin('otro_coach', 'otro@example.test');
        $ajeno = $this->program('Ajeno', [], $other->id);

        $this->postJson('/api/admin/training-program-set-macrocycle', ['id' => $ajeno->id, 'macrocycle_name' => 'X'])
            ->assertNotFound();
        $this->assertNull($ajeno->fresh()->macrocycle_name);
    }

    public function test_plan_devuelve_lo_planificado_por_semana_con_su_musculo(): void
    {
        $press = $this->exercise('Press banca', 'Pecho');
        $remo = $this->exercise('Remo', 'Dorsales');

        $m1 = $this->program('Macrociclo X - Mesociclo 1', [
            1 => [[$press, ['series' => '4', 'reps' => '8-10', 'rir' => '2', 'carga' => 'Mantener']], [$remo, ['series' => '3', 'reps' => '10', 'rpe' => '8']]],
            2 => [[$press, ['series' => '2', 'reps' => '12', 'rir' => '3', 'carga' => 'Bajar']]],
        ]);
        $other = $this->makeAdmin('otro_coach2', 'otro2@example.test');
        $ajeno = $this->program('Macrociclo X - Mesociclo 2', [], $other->id);

        $res = $this->getJson('/api/admin/macrocycle-plan?'.http_build_query(['program_ids' => [$m1->id, $ajeno->id]]))->assertOk();
        $data = $res->json('data');

        $this->assertSame([$ajeno->id], $data['skipped_ids']);
        $this->assertSame(1, $data['programs'][0]['mesocycle_number']);
        $this->assertCount(2, $data['sessions']);
        $this->assertTrue($data['sessions'][1]['is_deload']);
        $this->assertCount(3, $data['rows']);
        $this->assertSame(['Pecho', 'Dorsales', 'Pecho'], array_column($data['rows'], 'muscle'));
        $this->assertSame(['4', '3', '2'], array_column($data['rows'], 'series'));
        $this->assertSame('Mantener', $data['rows'][0]['carga']);
        $this->assertArrayHasKey('Dorsales', $data['muscle_splits']);
        $this->assertEquals(1.0, $data['muscle_splits']['Dorsales']['Dorsales']);
    }

    public function test_referencias_mev_mrv_por_defecto_y_editables(): void
    {
        $defaults = $this->getJson('/api/admin/macrocycle-references')->assertOk()->json('data');
        $this->assertEquals(['mev' => 10, 'mrv' => 20], $defaults['Dorsales']);

        $this->postJson('/api/admin/macrocycle-references-save', [
            'references' => ['Pecho' => ['mev' => 9, 'mrv' => 17], 'Core' => ['mev' => null, 'mrv' => 12]],
        ])->assertOk();

        $saved = $this->getJson('/api/admin/macrocycle-references')->json('data');
        $this->assertEquals(['Pecho' => ['mev' => 9, 'mrv' => 17], 'Core' => ['mev' => null, 'mrv' => 12]], $saved);
    }
}
