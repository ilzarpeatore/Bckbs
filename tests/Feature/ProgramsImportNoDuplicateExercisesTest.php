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
 * Regresión: un ejercicio que NO existe en el catálogo y aparece en varias sesiones del
 * mismo import se creaba una vez por sesión (4 sesiones = 4 ejercicios con el mismo nombre),
 * porque el matcher solo conocía los ejercicios de BD anteriores al import.
 */
class ProgramsImportNoDuplicateExercisesTest extends TestCase
{
    use RefreshDatabase;

    private function buildXlsx(array $days): string
    {
        $spreadsheet = new Spreadsheet();

        $programSheet = $spreadsheet->getActiveSheet();
        $programSheet->setTitle('Programa');
        $programSheet->fromArray(['titulo', 'descripcion', 'semanas'], null, 'A1');
        $programSheet->fromArray(['Programa sin duplicados', 'Fixture de test', 1], null, 'A2');

        $gridSheet = $spreadsheet->createSheet();
        $gridSheet->setTitle('Programación');
        $header = ['semana', 'dia', 'nombre_dia', 'es_descanso', 'notas_dia', 'bloque', 'instrucciones_bloque',
            'ejercicio', 'equipo', 'series', 'reps', 'rir', 'rpe', 'carga_kg', 'carga_pct',
            'descanso_seg', 'tempo', 'duracion_seg', 'notas'];
        $gridSheet->fromArray($header, null, 'A1');

        $row = 2;
        foreach ($days as $dia => $exercises) {
            foreach ($exercises as $name) {
                $gridSheet->fromArray(
                    [1, $dia, "Día $dia", null, null, null, null, $name, null, 3, '8-10', '2', null, null, null, null, null, null, null],
                    null,
                    "A{$row}"
                );
                $row++;
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function importAs(User $coach, string $path): void
    {
        $exitCode = Artisan::call('programs:import', [
            'source' => 'excel',
            'file' => $path,
            '--coach-id' => $coach->id,
            '--json' => true,
        ]);
        $this->assertSame(0, $exitCode);
    }

    private function coach(): User
    {
        return User::create([
            'first_name' => 'Coach',
            'last_name' => 'Fixture',
            'username' => 'coach_fixture_dup',
            'email' => 'coach-fixture-dup@example.test',
            'password' => bcrypt('password'),
            'user_type' => 'coach',
            'status' => 'active',
            'login_type' => 'manual',
        ]);
    }

    public function test_an_unknown_exercise_repeated_across_sessions_is_created_once(): void
    {
        $coach = $this->coach();
        // 4 sesiones distintas (cada una lleva además un ejercicio propio para que no se deduplique la plantilla)
        $path = $this->buildXlsx([
            1 => ['Xqzv Wibblefrump Zzqx', 'Kwlpr Alfa Zzqx'],
            2 => ['Xqzv Wibblefrump Zzqx', 'Vrndt Bravo Zzqx'],
            3 => ['Xqzv Wibblefrump Zzqx', 'Jhgfs Charlie Zzqx'],
            4 => ['Xqzv Wibblefrump Zzqx', 'Mbnxc Delta Zzqx'],
        ]);

        $this->importAs($coach, $path);
        unlink($path);

        $this->assertSame(1, Exercise::where('title', 'Xqzv Wibblefrump Zzqx')->count());
    }
}
