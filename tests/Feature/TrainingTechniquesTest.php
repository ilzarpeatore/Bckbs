<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ProgramDayAssignment;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use App\Support\TrainingTechniques;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Técnicas especiales en el `prescribed` de cada ejercicio (App\Support\TrainingTechniques):
 * catálogo, normalización, editor de sesiones, importador Excel y dashboard.
 */
class TrainingTechniquesTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        TrainingTechniques::flush();
        Role::findOrCreate('admin', 'web');

        $this->coach = User::create([
            'first_name' => 'Coach',
            'last_name'  => 'Tecnicas',
            'username'   => 'coach_tecnicas',
            'email'      => 'coach-tecnicas@example.test',
            'password'   => bcrypt('password'),
            'user_type'  => 'coach',
            'status'     => 'active',
            'login_type' => 'manual',
        ]);
        $this->coach->assignRole('admin');
    }

    public function test_resolve_acepta_slug_etiqueta_y_texto_libre(): void
    {
        $this->assertSame(['rest_pause', null], TrainingTechniques::resolve('Rest-Pause'));
        $this->assertSame(['drop_sets_mecanicos', null], TrainingTechniques::resolve('drop sets mecánicos'));
        $this->assertSame(['bfr', null], TrainingTechniques::resolve('bfr'));
        $this->assertSame(['otra', 'Pausa en el pecho'], TrainingTechniques::resolve('Pausa en el pecho'));
        $this->assertNull(TrainingTechniques::resolve('  '));

        $this->assertSame('ultima', TrainingTechniques::resolveSeries('Última'));
        $this->assertSame('ultima', TrainingTechniques::resolveSeries('ultima serie'));
        $this->assertSame('todas', TrainingTechniques::resolveSeries(null));
        $this->assertSame('todas', TrainingTechniques::resolveSeries('todas'));
    }

    public function test_normalize_prescribed(): void
    {
        $this->assertSame(
            ['series' => '3', 'tecnica' => 'drop_sets', 'tecnica_series' => 'todas'],
            TrainingTechniques::normalizePrescribed(['series' => '3', 'tecnica' => 'drop_sets', 'tecnica_otra' => 'x'])
        );
        // Sin técnica no quedan restos
        $this->assertSame(['series' => '3'], TrainingTechniques::normalizePrescribed(['series' => '3', 'tecnica_series' => 'ultima']));

        $this->expectException(ValidationException::class);
        TrainingTechniques::normalizePrescribed(['tecnica' => 'otra']);
    }

    public function test_slug_desconocido_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        TrainingTechniques::normalizePrescribed(['tecnica' => 'inventada']);
    }

    public function test_catalogo_para_panel_y_app(): void
    {
        Sanctum::actingAs($this->coach);

        $admin = $this->getJson('/api/admin/training-technique-list')->assertOk()->json('data');
        $this->assertContains('rest_pause', array_column($admin, 'key'));
        $this->assertSame('otra', end($admin)['key']);
        // Cada técnica del catálogo trae su ficha completa para la app
        foreach ($admin as $item) {
            $this->assertNotSame('', $item['label']);
            $this->assertNotSame('', $item['description']);
            $this->assertNotSame('', $item['logging']);
            if ($item['key'] !== 'otra') {
                $this->assertGreaterThanOrEqual(3, count($item['steps']), $item['key']);
                $this->assertNotEmpty($item['mistakes'], $item['key']);
            }
        }

        $this->getJson('/api/v1/training-technique-list')->assertOk()->assertJsonFragment(['key' => 'cluster_sets']);
    }

    public function test_el_editor_de_sesiones_guarda_la_tecnica_y_el_dashboard_la_devuelve(): void
    {
        Sanctum::actingAs($this->coach);
        $exercise = Exercise::create(['title' => 'Elevación lateral', 'status' => 'active']);
        $program = TrainingProgram::create(['title' => 'Macro - Mesociclo 1', 'coach_id' => $this->coach->id, 'num_weeks' => 1, 'is_personal' => false, 'activo' => true]);
        $template = WorkoutTemplate::create(['coach_id' => $this->coach->id, 'title' => 'Macro - Mesociclo 1 · Torso (S1)']);
        $block = WorkoutTemplateBlock::create(['workout_template_id' => $template->id, 'title' => 'Principal', 'order' => 1]);
        $row = WorkoutTemplateExercise::create([
            'workout_template_block_id' => $block->id, 'exercise_id' => $exercise->id, 'sequence' => 1,
            'prescribed' => ['series' => '3', 'reps' => '12'], 'enabled_metrics' => ['reps', 'carga', 'rir'],
        ]);
        $assignment = ProgramDayAssignment::create([
            'training_program_id' => $program->id, 'week_number' => 1, 'day_of_week' => 1, 'workout_template_id' => $template->id,
        ]);

        $save = fn (array $prescribed) => $this->postJson('/api/admin/program-session-matrix-save', [
            'training_program_id' => $program->id,
            'changes' => [['type' => 'update', 'assignment_id' => $assignment->id, 'row_id' => $row->id, 'prescribed' => $prescribed]],
        ]);

        $save(['tecnica' => 'rest_pause', 'tecnica_series' => 'ultima'])->assertOk();
        $this->assertSame(
            ['series' => '3', 'reps' => '12', 'tecnica' => 'rest_pause', 'tecnica_series' => 'ultima'],
            $row->fresh()->prescribed
        );

        $plan = $this->getJson('/api/admin/macrocycle-plan?'.http_build_query(['program_ids' => [$program->id]]))->json('data');
        $this->assertSame('rest_pause', $plan['rows'][0]['tecnica']);
        $this->assertSame('ultima', $plan['rows'][0]['tecnica_series']);

        // Técnica desconocida: rechazada, no se guarda
        $save(['tecnica' => 'inventada'])->assertStatus(422);
        $this->assertSame('rest_pause', $row->fresh()->prescribed['tecnica']);

        // Quitarla limpia también el alcance
        $save(['tecnica' => null])->assertOk();
        $this->assertSame(['series' => '3', 'reps' => '12'], $row->fresh()->prescribed);
    }

    public function test_el_importador_excel_lee_tecnica_y_tecnica_series(): void
    {
        Exercise::create(['title' => 'Elevaciones Laterales Fixture', 'status' => 'active']);

        $spreadsheet = new Spreadsheet();
        $programSheet = $spreadsheet->getActiveSheet();
        $programSheet->setTitle('Programa');
        $programSheet->fromArray(['titulo', 'descripcion', 'semanas'], null, 'A1');
        $programSheet->fromArray(['Programa técnicas', 'Fixture', 2], null, 'A2');
        $grid = $spreadsheet->createSheet();
        $grid->setTitle('Programación');
        $grid->fromArray(['semana', 'dia', 'nombre_dia', 'es_descanso', 'notas_dia', 'bloque', 'instrucciones_bloque',
            'ejercicio', 'equipo', 'series', 'reps', 'rir', 'rpe', 'carga_kg', 'carga_pct',
            'descanso_seg', 'tempo', 'duracion_seg', 'notas', 'tecnica', 'tecnica_series'], null, 'A1');
        $grid->fromArray([1, 1, 'Día 1', null, null, null, null, 'Elevaciones Laterales Fixture', null, 3, '12-15', '1', null, null, null, null, null, null, null, 'Rest-pause', 'última'], null, 'A2');
        $grid->fromArray([2, 1, 'Día 1', null, null, null, null, 'Elevaciones Laterales Fixture', null, 3, '12-15', '1', null, null, null, null, null, null, null, 'Pausa arriba 2 s', null], null, 'A3');
        $path = tempnam(sys_get_temp_dir(), 'test_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $exit = Artisan::call('programs:import', ['source' => 'excel', 'file' => $path, '--coach-id' => $this->coach->id, '--json' => true]);
        unlink($path);
        $this->assertSame(0, $exit, Artisan::output());

        $prescribed = WorkoutTemplateExercise::orderBy('id')->get()->pluck('prescribed');
        $this->assertSame('rest_pause', $prescribed[0]['tecnica']);
        $this->assertSame('ultima', $prescribed[0]['tecnica_series']);
        $this->assertSame('otra', $prescribed[1]['tecnica']);
        $this->assertSame('Pausa arriba 2 s', $prescribed[1]['tecnica_otra']);
        $this->assertSame('todas', $prescribed[1]['tecnica_series']);
    }

    public function test_el_coach_edita_los_textos_y_la_app_los_recibe(): void
    {
        Sanctum::actingAs($this->coach);

        $this->postJson('/api/admin/training-technique-save', [
            'key'         => 'rest_pause',
            'label'       => 'Rest-pause (BS)',
            'description' => 'Mi explicación.',
            'steps'       => ['Paso uno', '  ', 'Paso dos'],
            'mistakes'    => [],
            'logging'     => 'Apunta el total.',
        ])->assertOk();

        $app = collect($this->getJson('/api/v1/training-technique-list')->json('data'))->keyBy('key');
        $this->assertSame('Rest-pause (BS)', $app['rest_pause']['label']);
        $this->assertSame(['Paso uno', 'Paso dos'], $app['rest_pause']['steps']);
        $this->assertSame([], $app['rest_pause']['mistakes']);
        $this->assertTrue($app['rest_pause']['customized']);
        $this->assertFalse($app['drop_sets']['customized']);

        // El Excel reconoce tanto el nombre nuevo como el original
        $this->assertSame(['rest_pause', null], TrainingTechniques::resolve('Rest-pause (BS)'));
        $this->assertSame(['rest_pause', null], TrainingTechniques::resolve('rest-pause'));

        // Restaurar vuelve a los textos por defecto
        $this->postJson('/api/admin/training-technique-reset', ['key' => 'rest_pause'])->assertOk();
        $app = collect($this->getJson('/api/v1/training-technique-list')->json('data'))->keyBy('key');
        $this->assertSame('Rest-pause', $app['rest_pause']['label']);
        $this->assertFalse($app['rest_pause']['customized']);
    }

    public function test_no_se_pueden_crear_tecnicas_nuevas_ni_guardar_sin_nombre(): void
    {
        Sanctum::actingAs($this->coach);

        $this->postJson('/api/admin/training-technique-save', [
            'key' => 'inventada', 'label' => 'X', 'description' => 'Y', 'steps' => [], 'mistakes' => [],
        ])->assertStatus(422);
        $this->postJson('/api/admin/training-technique-save', [
            'key' => 'bfr', 'label' => '', 'description' => 'Y', 'steps' => [], 'mistakes' => [],
        ])->assertStatus(422);
    }
}
