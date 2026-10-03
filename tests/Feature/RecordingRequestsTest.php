<?php

namespace Tests\Feature;

use App\Models\ClientExerciseLog;
use App\Models\Exercise;
use App\Models\ProgramDayAssignment;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use App\Support\RecordingRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * «Pedir grabación» (App\Support\RecordingRequests): el coach marca un
 * ejercicio para que el cliente se grabe; viaja en el `prescribed` como la
 * técnica especial, y la serie grabada vuelve con `grabado: true`.
 */
class RecordingRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');

        $this->coach = User::create([
            'first_name' => 'Coach', 'last_name' => 'Grabar', 'username' => 'coach_grabar',
            'email' => 'coach-grabar@example.test', 'password' => bcrypt('password'),
            'user_type' => 'coach', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $this->coach->assignRole('admin');
        $this->client = User::create([
            'first_name' => 'Cli', 'last_name' => 'Ente', 'username' => 'cliente_grabar',
            'email' => 'cliente-grabar@example.test', 'password' => bcrypt('password'),
            'user_type' => 'user', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $this->client->assignRole('user');
    }

    /** Programa personal del cliente con una sesión y un ejercicio; devuelve [program, assignment, row]. */
    private function seedSession(bool $personal = true): array
    {
        $exercise = Exercise::create(['title' => 'Sentadilla', 'status' => 'active']);
        $program = TrainingProgram::create(['title' => 'Plan de Cli', 'coach_id' => $this->coach->id, 'num_weeks' => 1, 'is_personal' => $personal, 'personal_client_id' => $personal ? $this->client->id : null, 'activo' => true]);
        $template = WorkoutTemplate::create(['coach_id' => $this->coach->id, 'title' => 'Plan de Cli · Pierna (S1)']);
        $block = WorkoutTemplateBlock::create(['workout_template_id' => $template->id, 'title' => 'Principal', 'order' => 1]);
        $row = WorkoutTemplateExercise::create([
            'workout_template_block_id' => $block->id, 'exercise_id' => $exercise->id, 'sequence' => 1,
            'prescribed' => ['series' => '3', 'reps' => '8'], 'enabled_metrics' => ['reps', 'carga', 'rir'],
        ]);
        $assignment = ProgramDayAssignment::create([
            'training_program_id' => $program->id, 'week_number' => 1, 'day_of_week' => 1, 'workout_template_id' => $template->id,
        ]);
        if ($personal) {
            \App\Models\ProgramClientAssignment::create(['training_program_id' => $program->id, 'client_id' => $this->client->id, 'start_date' => now()->toDateString(), 'activo' => true]);
        }

        return [$program, $assignment, $row];
    }

    public function test_normalize_prescribed(): void
    {
        $this->assertSame(
            ['series' => '3', 'grabar' => true, 'grabar_series' => 'todas'],
            RecordingRequests::normalizePrescribed(['series' => '3', 'grabar' => '1', 'grabar_nota' => '  '])
        );
        $this->assertSame(
            ['grabar' => true, 'grabar_series' => 'ultima', 'grabar_nota' => 'De lado'],
            RecordingRequests::normalizePrescribed(['grabar' => 'sí', 'grabar_series' => 'Última', 'grabar_nota' => ' De lado '])
        );
        // Sin grabar no quedan restos; un `false` explícito (máscara de override) se conserva
        $this->assertSame(['series' => '3'], RecordingRequests::normalizePrescribed(['series' => '3', 'grabar' => 'no', 'grabar_series' => 'primera']));
        $this->assertSame(['grabar' => false], RecordingRequests::normalizePrescribed(['grabar' => false, 'grabar_nota' => 'x']));

        $this->assertSame('primera', RecordingRequests::resolveSeries('Primera serie'));
        $this->assertSame('ultima', RecordingRequests::resolveSeries('last'));
        $this->assertSame('todas', RecordingRequests::resolveSeries(null));
    }

    public function test_plantilla_override_del_cliente_y_la_app_lo_recibe(): void
    {
        [, $assignment, $row] = $this->seedSession();
        Sanctum::actingAs($this->coach);

        // 1) Plantilla, en la misma petición que la técnica
        $this->postJson('/api/admin/workout-template-exercise-technique', [
            'id' => $row->id, 'tecnica' => 'rest_pause', 'tecnica_series' => 'ultima',
            'grabar' => true, 'grabar_series' => 'ultima', 'grabar_nota' => 'De lado, que se vea la cadera',
        ])->assertOk();
        $this->assertSame([
            'series' => '3', 'reps' => '8', 'tecnica' => 'rest_pause', 'tecnica_series' => 'ultima',
            'grabar' => true, 'grabar_series' => 'ultima', 'grabar_nota' => 'De lado, que se vea la cadera',
        ], $row->fresh()->prescribed);

        // Un panel antiguo (sin `grabar`) cambia la técnica sin tocar la grabación
        $this->postJson('/api/admin/workout-template-exercise-technique', ['id' => $row->id, 'tecnica' => null])->assertOk();
        $this->assertTrue($row->fresh()->prescribed['grabar']);
        $this->assertArrayNotHasKey('tecnica', $row->fresh()->prescribed);

        // Validación
        $this->postJson('/api/admin/workout-template-exercise-technique', ['id' => $row->id, 'grabar' => true, 'grabar_series' => 'segunda'])->assertStatus(422);
        $this->postJson('/api/admin/workout-template-exercise-technique', ['id' => $row->id, 'grabar' => true, 'grabar_nota' => str_repeat('x', 201)])->assertStatus(422);

        Sanctum::actingAs($this->client);
        $sets = $this->getJson('/api/v1/my-calendar-day-detail?program_day_assignment_id='.$assignment->id)->assertOk()->json('data.blocks.0.exercises.0.sets');
        $this->assertTrue($sets['grabar']);
        $this->assertSame('ultima', $sets['grabar_series']);
        $this->assertSame('De lado, que se vea la cadera', $sets['grabar_nota']);

        // 2) Solo este cliente: quitarla tapa la de la plantilla
        Sanctum::actingAs($this->coach);
        $override = fn (array $t) => $this->postJson('/api/admin/session-detail-update-override-technique', [
            'program_day_assignment_id' => $assignment->id, 'client_id' => $this->client->id, 'workout_template_exercise_id' => $row->id,
        ] + $t);
        $override(['tecnica' => null, 'grabar' => false])->assertOk();
        $this->assertTrue($row->fresh()->prescribed['grabar']);

        Sanctum::actingAs($this->client);
        $sets = $this->getJson('/api/v1/my-calendar-day-detail?program_day_assignment_id='.$assignment->id)->json('data.blocks.0.exercises.0.sets');
        $this->assertFalse($sets['grabar']);

        // 3) Y pedirla solo a este cliente, primera serie
        Sanctum::actingAs($this->coach);
        $override(['tecnica' => null, 'grabar' => true, 'grabar_series' => 'primera'])->assertOk();
        Sanctum::actingAs($this->client);
        $sets = $this->getJson('/api/v1/my-calendar-day-detail?program_day_assignment_id='.$assignment->id)->json('data.blocks.0.exercises.0.sets');
        $this->assertTrue($sets['grabar']);
        $this->assertSame('primera', $sets['grabar_series']);
    }

    public function test_el_editor_de_sesiones_y_el_dashboard(): void
    {
        // El dashboard de macrociclos solo lista programas no personales
        [$program, $assignment, $row] = $this->seedSession(false);
        Sanctum::actingAs($this->coach);

        $save = fn (array $prescribed) => $this->postJson('/api/admin/program-session-matrix-save', [
            'training_program_id' => $program->id,
            'changes' => [['type' => 'update', 'assignment_id' => $assignment->id, 'row_id' => $row->id, 'prescribed' => $prescribed]],
        ]);

        $save(['grabar' => true, 'grabar_series' => 'primera', 'grabar_nota' => 'De frente'])->assertOk();
        $this->assertSame(
            ['series' => '3', 'reps' => '8', 'grabar' => true, 'grabar_series' => 'primera', 'grabar_nota' => 'De frente'],
            $row->fresh()->prescribed
        );

        $plan = $this->getJson('/api/admin/macrocycle-plan?'.http_build_query(['program_ids' => [$program->id]]))->json('data');
        $this->assertTrue($plan['rows'][0]['grabar']);
        $this->assertSame('primera', $plan['rows'][0]['grabar_series']);
        $this->assertSame('De frente', $plan['rows'][0]['grabar_nota']);

        // Quitarla limpia también alcance y nota
        $save(['grabar' => null])->assertOk();
        $this->assertSame(['series' => '3', 'reps' => '8'], $row->fresh()->prescribed);
    }

    public function test_la_serie_grabada_se_guarda_y_el_coach_la_ve(): void
    {
        [, $assignment, $row] = $this->seedSession();
        Sanctum::actingAs($this->client);

        $this->postJson('/api/v1/my-calendar-log-sets', [
            'workout_template_exercise_id' => $row->id,
            'program_day_assignment_id'    => $assignment->id,
            'logged_sets'                  => [
                ['carga' => 100, 'reps' => 8, 'rir' => 2],
                ['carga' => 100, 'reps' => 8, 'rir' => 1, 'grabado' => true],
                ['carga' => 100, 'reps' => 7, 'rir' => 0, 'grabado' => false],
            ],
        ])->assertOk();

        $sets = ClientExerciseLog::latest('id')->first()->logged_sets;
        $this->assertArrayNotHasKey('grabado', $sets[0]);
        $this->assertTrue($sets[1]['grabado']);
        $this->assertArrayNotHasKey('grabado', $sets[2]);

        Sanctum::actingAs($this->coach);
        $detail = $this->getJson('/api/admin/session-detail?'.http_build_query(['program_day_assignment_id' => $assignment->id, 'client_id' => $this->client->id]))->assertOk()->json('data');
        $exercise = $detail['blocks'][0]['exercises'][0];
        $this->assertSame([false, true, false], array_column($exercise['sets'], 'grabado'));
    }

    public function test_el_importador_excel_lee_las_columnas_de_grabar(): void
    {
        Exercise::create(['title' => 'Sentadilla Fixture', 'status' => 'active']);

        $spreadsheet = new Spreadsheet();
        $programSheet = $spreadsheet->getActiveSheet();
        $programSheet->setTitle('Programa');
        $programSheet->fromArray(['titulo', 'descripcion', 'semanas'], null, 'A1');
        $programSheet->fromArray(['Programa grabar', 'Fixture', 2], null, 'A2');
        $grid = $spreadsheet->createSheet();
        $grid->setTitle('Programación');
        $grid->fromArray(['semana', 'dia', 'nombre_dia', 'es_descanso', 'notas_dia', 'bloque', 'instrucciones_bloque',
            'ejercicio', 'equipo', 'series', 'reps', 'rir', 'rpe', 'carga_kg', 'carga_pct',
            'descanso_seg', 'tempo', 'duracion_seg', 'notas', 'tecnica', 'tecnica_series', 'grabar', 'grabar_series', 'grabar_nota'], null, 'A1');
        $grid->fromArray([1, 1, 'Día 1', null, null, null, null, 'Sentadilla Fixture', null, 3, '6-8', '2', null, null, null, null, null, null, null, null, null, 'sí', 'última', 'De lado'], null, 'A2');
        $grid->fromArray([2, 1, 'Día 1', null, null, null, null, 'Sentadilla Fixture', null, 3, '6-8', '2', null, null, null, null, null, null, null, null, null, null, null, null], null, 'A3');
        $path = tempnam(sys_get_temp_dir(), 'test_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $exit = Artisan::call('programs:import', ['source' => 'excel', 'file' => $path, '--coach-id' => $this->coach->id, '--json' => true]);
        unlink($path);
        $this->assertSame(0, $exit, Artisan::output());

        $prescribed = WorkoutTemplateExercise::orderBy('id')->get()->pluck('prescribed');
        $this->assertTrue($prescribed[0]['grabar']);
        $this->assertSame('ultima', $prescribed[0]['grabar_series']);
        $this->assertSame('De lado', $prescribed[0]['grabar_nota']);
        $this->assertArrayNotHasKey('grabar', $prescribed[1]);
        $this->assertSame(1, DB::table('workout_template_exercises')->where('prescribed', 'like', '%grabar%')->count());
    }
}
