<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Equivalente HTTP de tests/Feature/ProgramsImportConfidenceGateTest.php --
 * confirma que `confidence_gate` y `check_integrity` en POST program-import
 * (ver ProgramImportController) se comportan igual que sus homónimos --confidence-gate
 * y --check-integrity de la CLI.
 */
class ProgramImportControllerConfidenceGateTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');

        $this->coach = User::create([
            'first_name' => 'Coach',
            'last_name' => 'Fixture',
            'username' => 'coach_fixture_http',
            'email' => 'coach-fixture-http@example.test',
            'password' => bcrypt('password'),
            'user_type' => 'coach',
            'status' => 'active',
            'login_type' => 'manual',
        ]);
        $this->coach->assignRole('admin');
    }

    private function buildXlsxFile(string $exerciseName): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $programSheet = $spreadsheet->getActiveSheet();
        $programSheet->setTitle('Programa');
        $programSheet->fromArray(['titulo', 'descripcion', 'semanas'], null, 'A1');
        $programSheet->fromArray(['Programa HTTP test', 'Fixture', 1], null, 'A2');

        $gridSheet = $spreadsheet->createSheet();
        $gridSheet->setTitle('Programación');
        $header = ['semana', 'dia', 'nombre_dia', 'es_descanso', 'notas_dia', 'bloque', 'instrucciones_bloque',
            'ejercicio', 'equipo', 'series', 'reps', 'rir', 'rpe', 'carga_kg', 'carga_pct',
            'descanso_seg', 'tempo', 'duracion_seg', 'notas'];
        $gridSheet->fromArray($header, null, 'A1');
        $gridSheet->fromArray([1, 1, 'Día 1', null, null, null, null, $exerciseName, null, 3, '8-10', '2', null, null, null, null, null, null, null], null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'test_http_import_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'programa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_confidence_gate_blocks_real_import_via_http(): void
    {
        Sanctum::actingAs($this->coach, ['*']);

        $response = $this->postJson('/api/admin/program-import', [
            'file' => $this->buildXlsxFile('Ejercicio HTTP Inexistente Zzqx'),
            'dry_run' => false,
            'confidence_gate' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'confidence_gate_blocked');
        $this->assertNotEmpty($response->json('review_required'));
        $this->assertDatabaseCount('training_programs', 0);
    }

    public function test_confidence_gate_allows_real_import_via_http_when_match_is_level_a(): void
    {
        Exercise::create(['title' => 'Ejercicio HTTP Con Match', 'status' => 'active']);
        Sanctum::actingAs($this->coach, ['*']);

        $response = $this->postJson('/api/admin/program-import', [
            'file' => $this->buildXlsxFile('Ejercicio HTTP Con Match'),
            'dry_run' => false,
            'confidence_gate' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('training_programs', 1);
    }

    public function test_check_integrity_output_included_via_http(): void
    {
        Sanctum::actingAs($this->coach, ['*']);

        $response = $this->postJson('/api/admin/program-import', [
            'file' => $this->buildXlsxFile('Ejercicio HTTP Check Integrity'),
            'dry_run' => false,
            'check_integrity' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['check_integrity_output']);
        $this->assertStringContainsString('Sin referencias rotas', $response->json('check_integrity_output'));
    }

    public function test_dry_run_default_behavior_unaffected(): void
    {
        Sanctum::actingAs($this->coach, ['*']);

        $response = $this->postJson('/api/admin/program-import', [
            'file' => $this->buildXlsxFile('Ejercicio HTTP Dry Run'),
            // dry_run se omite -- debe seguir siendo TRUE por defecto
            'confidence_gate' => true,
            'check_integrity' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('dry_run', true);
        $response->assertJsonMissing(['check_integrity_output' => null]);
        $this->assertArrayNotHasKey('check_integrity_output', $response->json());
        $this->assertDatabaseCount('training_programs', 0);
    }
}
