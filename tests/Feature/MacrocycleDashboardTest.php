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
 * Página /macrociclos del panel: agrupación por macrociclo (solo manual: el
 * título nunca agrupa), datos planificados del dashboard y referencias MEV/MRV.
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

    public function test_nada_se_agrupa_solo_por_el_titulo(): void
    {
        // Títulos con sufijos distintos: antes cada uno salía como un macrociclo de un solo mesociclo
        $a = $this->program('Josu Mauricio — M1 · Calmar y construir la base (5 oct – 1 nov 2026)');
        $b = $this->program('Carlos Palomar -- Macrociclo 1 -- Mesociclo 2/5 -- Progresion de volumen');
        $c = $this->program('Macrociclo Carlos - Mesociclo 1');

        $res = $this->getJson('/api/admin/training-program-macrocycles')->assertOk();

        $this->assertSame([], $res->json('data'));
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], array_column($res->json('unassigned'), 'id'));

        // El título solo aporta una SUGERENCIA para rellenar el formulario, nunca la aplica
        $suggestions = collect($res->json('unassigned'))->keyBy('id');
        $this->assertSame('Macrociclo Carlos', $suggestions[$c->id]['suggested_macrocycle']);
        $this->assertSame(1, $suggestions[$c->id]['suggested_mesocycle']);
        $this->assertSame(2, $suggestions[$b->id]['suggested_mesocycle']);
        $this->assertNull($a->fresh()->macrocycle_name);
    }

    public function test_un_macrociclo_lo_forma_la_asignacion_manual(): void
    {
        $m1 = $this->program('Macrociclo Carlos - Mesociclo 1');
        $suelto = $this->program('Fuerza tren inferior');

        $this->postJson('/api/admin/training-program-set-macrocycle', [
            'id' => $m1->id, 'macrocycle_name' => 'Carlos', 'mesocycle_number' => 1,
        ])->assertOk();
        $this->postJson('/api/admin/training-program-set-macrocycle', [
            'id' => $suelto->id, 'macrocycle_name' => 'carlos', 'mesocycle_number' => 2,
        ])->assertOk();

        $groups = collect($this->getJson('/api/admin/training-program-macrocycles')->json('data'));
        $this->assertCount(1, $groups); // «Carlos» y «carlos» son el mismo macrociclo
        $this->assertSame([$m1->id, $suelto->id], array_column($groups[0]['mesocycles'], 'id'));
        $this->assertSame([1, 2], array_column($groups[0]['mesocycles'], 'mesocycle_number'));

        // Quitar la asignación lo devuelve a «sin macrociclo»
        $this->postJson('/api/admin/training-program-set-macrocycle', ['id' => $suelto->id, 'macrocycle_name' => null])->assertOk();
        $res = $this->getJson('/api/admin/training-program-macrocycles');
        $this->assertSame([$suelto->id], array_column($res->json('unassigned'), 'id'));
    }

    public function test_seleccion_en_bloque_forma_un_macrociclo_con_titulos_distintos(): void
    {
        $m1 = $this->program('Arafa — M1 · Base y control del hombro');
        $m2 = $this->program('Arafa — M2 · Construir volumen');
        $m3 = $this->program('Algo sin número en el título');
        $otro = $this->program('Fuerza tren inferior');

        $this->postJson('/api/admin/training-program-set-macrocycle-bulk', [
            'macrocycle_name' => '  Arafa - Macrociclo 1 ',
            'items' => [
                ['id' => $m1->id, 'mesocycle_number' => 1],
                ['id' => $m2->id, 'mesocycle_number' => 2],
                ['id' => $m3->id, 'mesocycle_number' => 3],
            ],
        ])->assertOk();

        $res = $this->getJson('/api/admin/training-program-macrocycles')->assertOk();
        $groups = collect($res->json('data'));
        $this->assertCount(1, $groups);
        $this->assertSame('Arafa - Macrociclo 1', $groups[0]['name']); // sin espacios sobrantes
        $this->assertSame([$m1->id, $m2->id, $m3->id], array_column($groups[0]['mesocycles'], 'id'));
        $this->assertSame([1, 2, 3], array_column($groups[0]['mesocycles'], 'mesocycle_number'));
        $this->assertSame(3, $groups[0]['mesocycles'][2]['mesocycle_number']);
        $this->assertSame([$otro->id], array_column($res->json('unassigned'), 'id'));
    }

    public function test_seleccion_en_bloque_es_todo_o_nada_y_solo_de_tu_coach(): void
    {
        $mio = $this->program('Mío');
        $other = $this->makeAdmin('otro_coach3', 'otro3@example.test');
        $ajeno = $this->program('Ajeno', [], $other->id);
        $personal = TrainingProgram::create([
            'title' => 'Calendario personal', 'coach_id' => $this->coach->id, 'is_personal' => true, 'activo' => true,
        ]);

        foreach ([$ajeno, $personal] as $intruso) {
            $this->postJson('/api/admin/training-program-set-macrocycle-bulk', [
                'macrocycle_name' => 'X',
                'items' => [['id' => $mio->id, 'mesocycle_number' => 1], ['id' => $intruso->id, 'mesocycle_number' => 2]],
            ])->assertNotFound();
        }

        $this->assertNull($mio->fresh()->macrocycle_name); // no se tocó ninguno
        $this->assertNull($ajeno->fresh()->macrocycle_name);
    }

    public function test_seleccion_en_bloque_valida_la_entrada_y_puede_disolver(): void
    {
        $a = $this->program('A');
        $b = $this->program('B');

        $this->postJson('/api/admin/training-program-set-macrocycle-bulk', ['macrocycle_name' => 'X', 'items' => []])
            ->assertUnprocessable();
        $this->postJson('/api/admin/training-program-set-macrocycle-bulk', [
            'macrocycle_name' => 'X', 'items' => [['id' => $a->id], ['id' => $a->id]],
        ])->assertUnprocessable(); // ids repetidos

        $this->postJson('/api/admin/training-program-set-macrocycle-bulk', [
            'macrocycle_name' => 'X', 'items' => [['id' => $a->id, 'mesocycle_number' => 1], ['id' => $b->id]],
        ])->assertOk();
        $this->assertSame('X', $b->fresh()->macrocycle_name);
        $this->assertNull($b->fresh()->mesocycle_number);

        // Sin nombre = disolver: quita nombre y número de todos
        $this->postJson('/api/admin/training-program-set-macrocycle-bulk', [
            'macrocycle_name' => null, 'items' => [['id' => $a->id], ['id' => $b->id]],
        ])->assertOk();
        $this->assertNull($a->fresh()->macrocycle_name);
        $this->assertNull($a->fresh()->mesocycle_number);
        $this->assertSame([], $this->getJson('/api/admin/training-program-macrocycles')->json('data'));
    }

    public function test_copia_por_cliente_hereda_el_macrociclo_pero_es_otro_grupo(): void
    {
        $lib = $this->program('Biblioteca M1');
        $lib->update(['macrocycle_name' => 'Macro Z', 'mesocycle_number' => 1]);
        $client = User::create([
            'first_name' => 'Cli', 'last_name' => 'Ente', 'username' => 'cli_z', 'email' => 'cli-z@example.test',
            'password' => bcrypt('password'), 'user_type' => 'user', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $lib->fresh()->cloneForClient($client->id); // fresh(): trae los valores por defecto de la BD (is_free_accessible)

        $groups = collect($this->getJson('/api/admin/training-program-macrocycles')->json('data'));
        $this->assertCount(2, $groups); // biblioteca y copia del cliente, cada uno en su grupo
        $this->assertEqualsCanonicalizing([null, $client->id], $groups->map(fn ($g) => $g['client']['id'] ?? null)->all());
        $this->assertSame(['Macro Z', 'Macro Z'], $groups->pluck('name')->all());
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
