<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use App\Services\ProgramCloningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 6 de docs/PLAN_CLONADO_PROGRAMAS.md -- `programs:backfill-clones`.
 *
 * Reproduce el escenario real de producción antes del cutover: 2 (o más)
 * `ProgramClientAssignment` de clientes DISTINTOS apuntando al mismo
 * `training_program_id` de biblioteca (sin clonar, como está hoy). No pasa
 * por `ProgramAssignmentService` ni por el feature flag -- las filas se
 * crean directo, igual que las dejó el comportamiento pre-Fase 2.
 */
class BackfillProgramClonesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCoach(): User
    {
        return User::create([
            'first_name' => 'Coach',
            'last_name'  => 'Backfill',
            'username'   => 'coach_' . uniqid(),
            'email'      => uniqid() . '@example.test',
            'password'   => bcrypt('password'),
            'user_type'  => 'coach',
            'status'     => 'active',
            'login_type' => 'manual',
        ]);
    }

    private function makeClient(int $coachId): User
    {
        $client = User::create([
            'first_name' => 'Client',
            'last_name'  => 'Backfill',
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

    private function makeExercise(string $title = 'Ejercicio de prueba'): Exercise
    {
        return Exercise::create(['title' => $title, 'status' => 'active']);
    }

    private function makeWorkoutTemplate(User $coach, Exercise $exercise, string $title): WorkoutTemplate
    {
        $template = WorkoutTemplate::create([
            'coach_id' => $coach->id,
            'title'    => $title,
        ]);

        $block = WorkoutTemplateBlock::create([
            'workout_template_id' => $template->id,
            'title'                => 'Bloque principal',
            'instructions'         => 'Descansa 90s entre series',
            'order'                => 1,
        ]);

        WorkoutTemplateExercise::create([
            'workout_template_block_id' => $block->id,
            'exercise_id'                => $exercise->id,
            'sequence'                   => 1,
            'prescribed'                 => ['series' => '3x10'],
            'enabled_metrics'            => ['reps', 'weight'],
            'notes'                      => 'Nota de la plantilla',
        ]);

        return $template;
    }

    /** Programa de biblioteca de 1 semana / 1 día con entrenamiento. */
    private function makeLibraryProgram(User $coach, Exercise $exercise): TrainingProgram
    {
        $template = $this->makeWorkoutTemplate($coach, $exercise, 'Plantilla biblioteca ' . uniqid());

        $program = TrainingProgram::create([
            'title'     => 'Programa de biblioteca ' . uniqid(),
            'coach_id'  => $coach->id,
            'num_weeks' => 1,
            'activo'    => true,
        ]);

        ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'          => 1,
            'day_of_week'          => 1,
            'workout_template_id'  => $template->id,
        ]);

        return $program;
    }

    /** Asignación tal y como quedaba ANTES de la Fase 2 -- sin clonar, apunta directo a biblioteca. */
    private function makeUnclonedAssignment(TrainingProgram $libraryProgram, User $client): ProgramClientAssignment
    {
        return ProgramClientAssignment::create([
            'training_program_id' => $libraryProgram->id,
            'client_id'            => $client->id,
            'start_date'           => now()->toDateString(),
            'fecha_fin'            => now()->addWeeks($libraryProgram->num_weeks)->toDateString(),
            'activo'               => true,
        ]);
    }

    public function test_dry_run_reports_pending_assignments_without_writing(): void
    {
        $coach = $this->makeCoach();
        $exercise = $this->makeExercise();
        $library = $this->makeLibraryProgram($coach, $exercise);

        $clientA = $this->makeClient($coach->id);
        $clientB = $this->makeClient($coach->id);
        $assignmentA = $this->makeUnclonedAssignment($library, $clientA);
        $assignmentB = $this->makeUnclonedAssignment($library, $clientB);

        $this->artisan('programs:backfill-clones')
            ->expectsOutputToContain((string) $library->id)
            ->expectsOutputToContain('Total: 2 asignación(es) se convertirían en clones')
            ->assertExitCode(0);

        // La BD no cambió en absoluto.
        $this->assertSame($library->id, $assignmentA->fresh()->training_program_id);
        $this->assertSame($library->id, $assignmentB->fresh()->training_program_id);
        $this->assertDatabaseCount('training_programs', 1);
        $this->assertFalse($library->fresh()->is_client_copy);
    }

    public function test_apply_clones_each_assignment_independently(): void
    {
        $coach = $this->makeCoach();
        $exercise = $this->makeExercise();
        $library = $this->makeLibraryProgram($coach, $exercise);
        $originalDayCount = $library->dayAssignments()->count();
        $originalTemplate = $library->dayAssignments->first()->workoutTemplate;
        $originalExerciseCount = $originalTemplate->blocks->first()->exercises->count();

        $clientA = $this->makeClient($coach->id);
        $clientB = $this->makeClient($coach->id);
        $assignmentA = $this->makeUnclonedAssignment($library, $clientA);
        $assignmentB = $this->makeUnclonedAssignment($library, $clientB);

        $this->artisan('programs:backfill-clones', ['--apply' => true])
            ->expectsOutputToContain('2 clonada(s), 0 saltada(s) (ya tenían clon), 0 fallida(s).')
            ->assertExitCode(0);

        $assignmentA->refresh();
        $assignmentB->refresh();

        $this->assertNotSame($library->id, $assignmentA->training_program_id);
        $this->assertNotSame($library->id, $assignmentB->training_program_id);
        $this->assertNotSame($assignmentA->training_program_id, $assignmentB->training_program_id, 'Cada cliente debe terminar con su PROPIO clon, no uno compartido.');

        $cloneA = TrainingProgram::find($assignmentA->training_program_id);
        $cloneB = TrainingProgram::find($assignmentB->training_program_id);

        $this->assertTrue($cloneA->is_client_copy);
        $this->assertTrue($cloneB->is_client_copy);
        $this->assertSame($library->id, $cloneA->source_training_program_id);
        $this->assertSame($library->id, $cloneB->source_training_program_id);

        // El programa de biblioteca original queda intacto -- sus propios
        // ProgramDayAssignment/WorkoutTemplate no se han tocado.
        $library->refresh();
        $this->assertFalse($library->is_client_copy);
        $this->assertNull($library->source_training_program_id);
        $this->assertSame($originalDayCount, $library->dayAssignments()->count());
        $this->assertSame($originalTemplate->id, $library->dayAssignments->first()->workout_template_id);
        $this->assertSame($originalExerciseCount, $originalTemplate->fresh()->blocks->first()->exercises->count());
    }

    public function test_apply_is_idempotent_on_second_run(): void
    {
        $coach = $this->makeCoach();
        $exercise = $this->makeExercise();
        $library = $this->makeLibraryProgram($coach, $exercise);

        $clientA = $this->makeClient($coach->id);
        $clientB = $this->makeClient($coach->id);
        $this->makeUnclonedAssignment($library, $clientA);
        $this->makeUnclonedAssignment($library, $clientB);

        $this->artisan('programs:backfill-clones', ['--apply' => true])->assertExitCode(0);

        $trainingProgramCountAfterFirstRun = TrainingProgram::count();
        $workoutTemplateCountAfterFirstRun = WorkoutTemplate::count();

        $this->artisan('programs:backfill-clones', ['--apply' => true])
            ->expectsOutputToContain('0 clonada(s), 2 saltada(s) (ya tenían clon), 0 fallida(s).')
            ->assertExitCode(0);

        $this->assertSame($trainingProgramCountAfterFirstRun, TrainingProgram::count());
        $this->assertSame($workoutTemplateCountAfterFirstRun, WorkoutTemplate::count());
    }

    public function test_apply_only_touches_the_assignment_still_pointing_at_library(): void
    {
        $coach = $this->makeCoach();
        $exercise = $this->makeExercise();
        $library = $this->makeLibraryProgram($coach, $exercise);

        $clientAlreadyCloned = $this->makeClient($coach->id);
        $clientPending = $this->makeClient($coach->id);

        // Cliente A: ya migrado manualmente (simula una asignación NUEVA
        // creada ya con el flag de clonado activo, Fase 2) -- su
        // TrainingProgram ya es un clon de verdad.
        $alreadyClonedProgram = (new ProgramCloningService())->clone($library->fresh(), $clientAlreadyCloned->id);
        $assignmentAlreadyCloned = $this->makeUnclonedAssignment($library, $clientAlreadyCloned);
        $assignmentAlreadyCloned->update(['training_program_id' => $alreadyClonedProgram->id]);

        // Cliente B: pendiente, apunta aún a la plantilla de biblioteca.
        $assignmentPending = $this->makeUnclonedAssignment($library, $clientPending);

        $this->artisan('programs:backfill-clones', ['--apply' => true])
            ->expectsOutputToContain('1 clonada(s), 1 saltada(s) (ya tenían clon), 0 fallida(s).')
            ->assertExitCode(0);

        $assignmentAlreadyCloned->refresh();
        $assignmentPending->refresh();

        // El ya clonado no se tocó -- sigue apuntando a SU MISMO clon.
        $this->assertSame($alreadyClonedProgram->id, $assignmentAlreadyCloned->training_program_id);

        // El pendiente ahora tiene su propio clon, distinto del anterior.
        $this->assertNotSame($library->id, $assignmentPending->training_program_id);
        $this->assertNotSame($alreadyClonedProgram->id, $assignmentPending->training_program_id);
        $pendingClone = TrainingProgram::find($assignmentPending->training_program_id);
        $this->assertTrue($pendingClone->is_client_copy);
        $this->assertSame($library->id, $pendingClone->source_training_program_id);
    }
}
