<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Cubre los puntos 4 y 5 de docs/AGENTE_IMPORTADOR.md, sección 7 (mejoras
 * opcionales no bloqueantes, ver docs/TAREAS_PENDIENTES.md 4.1/4.2):
 * `--confidence-gate` convierte en guardrail de código la pausa que hasta
 * ahora solo imponía el LLM, y `--check-integrity` corre la comprobación
 * inmediatamente tras el import real en vez de esperar al cron semanal.
 *
 * No usa el catálogo real (no existe en este entorno de pruebas) -- construye
 * su propio catálogo mínimo con Exercise::create() para controlar a
 * propósito qué ejercicio matchea (nivel A) y cuál no matchea nada.
 */
class ProgramsImportConfidenceGateTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase envuelve cada test en una transacción que se
        // revierte al final -- MySQL NO revierte el contador AUTO_INCREMENT
        // con la transacción, así que el id de este usuario NO es fiable
        // como "1" entre tests. Pasar siempre --coach-id=$this->coach->id
        // explícito en vez de asumir el valor por defecto de la CLI.
        $this->coach = User::create([
            'first_name' => 'Coach',
            'last_name' => 'Fixture',
            'username' => 'coach_fixture',
            'email' => 'coach-fixture@example.test',
            'password' => bcrypt('password'),
            'user_type' => 'coach',
            'status' => 'active',
            'login_type' => 'manual',
        ]);
    }

    private function buildXlsx(string $exerciseName, int $weeks = 1): string
    {
        $spreadsheet = new Spreadsheet();

        $programSheet = $spreadsheet->getActiveSheet();
        $programSheet->setTitle('Programa');
        $programSheet->fromArray(['titulo', 'descripcion', 'semanas'], null, 'A1');
        $programSheet->fromArray(['Programa de prueba', 'Fixture de test', $weeks], null, 'A2');

        $gridSheet = $spreadsheet->createSheet();
        $gridSheet->setTitle('Programación');
        $header = ['semana', 'dia', 'nombre_dia', 'es_descanso', 'notas_dia', 'bloque', 'instrucciones_bloque',
            'ejercicio', 'equipo', 'series', 'reps', 'rir', 'rpe', 'carga_kg', 'carga_pct',
            'descanso_seg', 'tempo', 'duracion_seg', 'notas'];
        $gridSheet->fromArray($header, null, 'A1');

        $row = 2;
        for ($w = 1; $w <= $weeks; $w++) {
            $gridSheet->fromArray(
                [$w, 1, 'Día 1', null, null, null, null, $exerciseName, null, 3, '8-10', '2', null, null, null, null, null, null, null],
                null,
                "A{$row}"
            );
            $row++;
        }

        $path = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_confidence_gate_blocks_real_import_when_exercise_has_no_match(): void
    {
        $path = $this->buildXlsx('Ejercicio Totalmente Inexistente En El Catalogo Zzqx');

        $exitCode = Artisan::call('programs:import', [
            'source' => 'excel',
            'file' => $path,
            '--coach-id' => $this->coach->id,
            '--confidence-gate' => true,
            '--json' => true,
        ]);
        $output = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exitCode);
        $this->assertFalse($output['ok']);
        $this->assertSame('confidence_gate_blocked', $output['error']);
        $this->assertNotEmpty($output['review_required']);
        $this->assertDatabaseCount('training_programs', 0);

        unlink($path);
    }

    public function test_confidence_gate_allows_real_import_when_exercise_matches_level_a(): void
    {
        Exercise::create([
            'title' => 'Sentadilla Trasera Fixture',
            'status' => 'active',
        ]);

        $path = $this->buildXlsx('Sentadilla Trasera Fixture');

        $exitCode = Artisan::call('programs:import', [
            'source' => 'excel',
            'file' => $path,
            '--coach-id' => $this->coach->id,
            '--confidence-gate' => true,
            '--json' => true,
        ]);
        $output = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($output['ok']);
        $this->assertDatabaseCount('training_programs', 1);

        unlink($path);
    }

    public function test_confidence_gate_has_no_effect_on_dry_run(): void
    {
        $path = $this->buildXlsx('Ejercicio Totalmente Inexistente En El Catalogo Zzqx');

        $exitCode = Artisan::call('programs:import', [
            'source' => 'excel',
            'file' => $path,
            '--coach-id' => $this->coach->id,
            '--dry-run' => true,
            '--confidence-gate' => true,
            '--json' => true,
        ]);
        $output = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($output['ok']);
        $this->assertNotEmpty($output['review_required']); // se informa, pero no bloquea un dry-run

        unlink($path);
    }

    public function test_default_behavior_unchanged_without_confidence_gate_flag(): void
    {
        $path = $this->buildXlsx('Ejercicio Totalmente Inexistente En El Catalogo Zzqx');

        $exitCode = Artisan::call('programs:import', [
            'source' => 'excel',
            'file' => $path,
            '--coach-id' => $this->coach->id,
            '--json' => true,
        ]);
        $output = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($output['ok']);
        $this->assertDatabaseCount('training_programs', 1); // se creó, con un ejercicio auto-creado -- comportamiento previo intacto

        unlink($path);
    }

    public function test_check_integrity_runs_immediately_after_real_import_and_reports_no_broken_refs(): void
    {
        $path = $this->buildXlsx('Ejercicio Cualquiera Para Check Integrity');

        $exitCode = Artisan::call('programs:import', [
            'source' => 'excel',
            'file' => $path,
            '--coach-id' => $this->coach->id,
            '--check-integrity' => true,
            '--json' => true,
        ]);
        $output = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertArrayHasKey('check_integrity_output', $output);
        $this->assertStringContainsString('Sin referencias rotas', $output['check_integrity_output']);

        unlink($path);
    }

    public function test_check_integrity_not_run_on_dry_run(): void
    {
        $path = $this->buildXlsx('Ejercicio Cualquiera');

        Artisan::call('programs:import', [
            'source' => 'excel',
            'file' => $path,
            '--coach-id' => $this->coach->id,
            '--dry-run' => true,
            '--check-integrity' => true,
            '--json' => true,
        ]);
        $output = json_decode(Artisan::output(), true);

        $this->assertArrayNotHasKey('check_integrity_output', $output);

        unlink($path);
    }
}
