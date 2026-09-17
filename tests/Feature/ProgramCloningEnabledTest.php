<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ProgramClientAssignment;
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
 * Fase 2 de docs/PLAN_CLONADO_PROGRAMAS.md — mismos escenarios que
 * `ProgramCloningCharacterizationTest` (sección 2: "puntos de entrada de
 * asignación") pero con `PROGRAM_CLONING_ENABLED=true`. Al contrario que
 * esos tests (que documentan el bug con el flag OFF, el default), aquí se
 * verifica el comportamiento DESEADO: dos clientes asignados al mismo
 * programa de biblioteca terminan cada uno con su propio clon
 * (`ProgramClientAssignment.training_program_id` distinto para cada uno),
 * y una segunda llamada para el MISMO cliente+programa reutiliza su clon ya
 * existente en vez de crear uno nuevo cada vez.
 */
class ProgramCloningEnabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        config(['services.program_cloning.enabled' => true]);
    }

    private function makeCoach(): User
    {
        $coach = User::create([
            'first_name' => 'Coach',
            'last_name'  => 'Cloning',
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
            'last_name'  => 'Cloning',
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

    /** Mismo helper que ProgramCloningCharacterizationTest::makeLibraryProgram(). */
    private function makeLibraryProgram(User $coach, Exercise $exercise): array
    {
        $program = TrainingProgram::create([
            'title'     => 'Programa de biblioteca ' . uniqid(),
            'coach_id'  => $coach->id,
            'num_weeks' => 4,
            'activo'    => true,
        ]);

        $template = WorkoutTemplate::create([
            'coach_id' => $coach->id,
            'title'    => 'Plantilla compartida ' . uniqid(),
        ]);

        $block = WorkoutTemplateBlock::create([
            'workout_template_id' => $template->id,
            'title'                => 'Bloque principal',
            'order'                => 1,
        ]);

        WorkoutTemplateExercise::create([
            'workout_template_block_id' => $block->id,
            'exercise_id'                => $exercise->id,
            'sequence'                   => 1,
            'prescribed'                 => ['series' => '3x10'],
            'enabled_metrics'            => ['reps', 'weight'],
        ]);

        $dayAssignment = ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'          => 1,
            'day_of_week'          => 1,
            'workout_template_id'  => $template->id,
            'scheduled_date'       => now()->toDateString(),
        ]);

        return compact('program', 'template', 'block', 'dayAssignment', 'exercise');
    }

    // ═══ TrainingProgramController::assignClient ══════════════════════

    public function test_assign_client_endpoint_clones_a_separate_copy_per_client(): void
    {
        $coach = $this->makeCoach();
        $clientA = $this->makeClient($coach->id);
        $clientB = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();
        ['program' => $program] = $this->makeLibraryProgram($coach, $exercise);

        Sanctum::actingAs($coach, ['*']);

        $this->postJson('/api/admin/training-program-assign-client', [
            'training_program_id' => $program->id,
            'client_id'            => $clientA->id,
            'start_date'           => now()->toDateString(),
        ])->assertStatus(200);

        $this->postJson('/api/admin/training-program-assign-client', [
            'training_program_id' => $program->id,
            'client_id'            => $clientB->id,
            'start_date'           => now()->toDateString(),
        ])->assertStatus(200);

        // La plantilla de biblioteca sigue siendo una sola, pero ahora hay
        // además una copia exclusiva por cliente (2 clones).
        $this->assertDatabaseCount('training_programs', 3);

        $assignmentA = ProgramClientAssignment::where('client_id', $clientA->id)->firstOrFail();
        $assignmentB = ProgramClientAssignment::where('client_id', $clientB->id)->firstOrFail();

        $this->assertNotSame(
            $assignmentA->training_program_id,
            $assignmentB->training_program_id,
            'Con el flag de clonado activo, cada cliente debe tener su PROPIO clon, no compartir el id de biblioteca.'
        );
        $this->assertNotSame($program->id, $assignmentA->training_program_id);
        $this->assertNotSame($program->id, $assignmentB->training_program_id);

        $this->assertSame($program->id, TrainingProgram::find($assignmentA->training_program_id)->source_training_program_id);
        $this->assertSame($program->id, TrainingProgram::find($assignmentB->training_program_id)->source_training_program_id);
        $this->assertTrue(TrainingProgram::find($assignmentA->training_program_id)->is_client_copy);
    }

    public function test_assign_client_endpoint_reuses_the_same_clone_on_renewal(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();
        ['program' => $program] = $this->makeLibraryProgram($coach, $exercise);

        Sanctum::actingAs($coach, ['*']);

        $this->postJson('/api/admin/training-program-assign-client', [
            'training_program_id' => $program->id,
            'client_id'            => $client->id,
            'start_date'           => now()->toDateString(),
        ])->assertStatus(200);

        $firstAssignment = ProgramClientAssignment::where('client_id', $client->id)->firstOrFail();
        $firstCloneId = $firstAssignment->training_program_id;

        // Segunda llamada -- renovación, misma fila de asignación, mismo clon.
        $this->postJson('/api/admin/training-program-assign-client', [
            'training_program_id' => $program->id,
            'client_id'            => $client->id,
            'start_date'           => now()->addWeek()->toDateString(),
        ])->assertStatus(200);

        $this->assertDatabaseCount('program_client_assignments', 1);
        $this->assertSame($firstCloneId, $firstAssignment->fresh()->training_program_id);
        // No se creó un segundo clon.
        $this->assertDatabaseCount('training_programs', 2);
    }

    // ═══ ClientProfileCalendarController::importProgram ═══════════════

    public function test_import_program_endpoint_clones_a_separate_copy_per_client(): void
    {
        $coach = $this->makeCoach();
        $clientA = $this->makeClient($coach->id);
        $clientB = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();
        ['program' => $program] = $this->makeLibraryProgram($coach, $exercise);

        Sanctum::actingAs($coach, ['*']);

        $this->postJson('/api/admin/client-calendar-import-program', [
            'client_id'            => $clientA->id,
            'training_program_id' => $program->id,
            'start_date'           => now()->toDateString(),
        ])->assertStatus(200);

        $this->postJson('/api/admin/client-calendar-import-program', [
            'client_id'            => $clientB->id,
            'training_program_id' => $program->id,
            'start_date'           => now()->toDateString(),
        ])->assertStatus(200);

        $this->assertDatabaseCount('training_programs', 3);

        $assignmentA = ProgramClientAssignment::where('client_id', $clientA->id)->firstOrFail();
        $assignmentB = ProgramClientAssignment::where('client_id', $clientB->id)->firstOrFail();

        $this->assertNotSame(
            $assignmentA->training_program_id,
            $assignmentB->training_program_id,
            'Con el flag de clonado activo, "importar programa" también debe dejar a cada cliente con su propio clon.'
        );
        $this->assertSame($program->id, TrainingProgram::find($assignmentA->training_program_id)->source_training_program_id);
        $this->assertSame($program->id, TrainingProgram::find($assignmentB->training_program_id)->source_training_program_id);
    }

    // ═══ Aislamiento real (adelanto de Fase 4): añadir un ejercicio a la
    // sesión de A ya no debe verse desde B ni mutar la biblioteca ════════

    public function test_add_exercise_on_client_a_session_is_isolated_from_client_b_once_cloned(): void
    {
        $coach = $this->makeCoach();
        $clientA = $this->makeClient($coach->id);
        $clientB = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();
        $newExercise = $this->makeExercise('Ejercicio añadido por el coach para A');

        ['program' => $program, 'block' => $libraryBlock] = $this->makeLibraryProgram($coach, $exercise);

        Sanctum::actingAs($coach, ['*']);

        $this->postJson('/api/admin/training-program-assign-client', [
            'training_program_id' => $program->id,
            'client_id'            => $clientA->id,
            'start_date'           => now()->toDateString(),
        ])->assertStatus(200);

        $this->postJson('/api/admin/training-program-assign-client', [
            'training_program_id' => $program->id,
            'client_id'            => $clientB->id,
            'start_date'           => now()->toDateString(),
        ])->assertStatus(200);

        $assignmentA = ProgramClientAssignment::where('client_id', $clientA->id)->firstOrFail();
        $assignmentB = ProgramClientAssignment::where('client_id', $clientB->id)->firstOrFail();

        $dayAssignmentA = ProgramDayAssignment::where('training_program_id', $assignmentA->training_program_id)->firstOrFail();
        $dayAssignmentB = ProgramDayAssignment::where('training_program_id', $assignmentB->training_program_id)->firstOrFail();
        $blockA = WorkoutTemplateBlock::where('workout_template_id', $dayAssignmentA->workout_template_id)->firstOrFail();

        $this->postJson('/api/admin/session-detail-add-exercise', [
            'program_day_assignment_id' => $dayAssignmentA->id,
            'workout_template_block_id'  => $blockA->id,
            'exercise_id'                => $newExercise->id,
        ])->assertStatus(200);

        $detailB = $this->getJson('/api/admin/session-detail?' . http_build_query([
            'program_day_assignment_id' => $dayAssignmentB->id,
            'client_id'                  => $clientB->id,
        ]));
        $detailB->assertStatus(200);

        $exerciseIdsSeenByB = collect($detailB->json('data.blocks'))
            ->flatMap(fn ($b) => collect($b['exercises'])->pluck('exercise_id'));

        $this->assertFalse(
            $exerciseIdsSeenByB->contains($newExercise->id),
            'Tras el clonado, el cliente B (su propio WorkoutTemplate clon) NO debe ver el ejercicio añadido a la sesión de A.'
        );

        // Tampoco mutó el bloque de la plantilla de biblioteca original
        // (el nuevo ejercicio solo debe existir en el bloque del CLON de A).
        $this->assertDatabaseMissing('workout_template_exercises', [
            'workout_template_block_id' => $libraryBlock->id,
            'exercise_id'                => $newExercise->id,
        ]);
        $this->assertDatabaseHas('workout_template_exercises', [
            'workout_template_block_id' => $blockA->id,
            'exercise_id'                => $newExercise->id,
        ]);
    }
}
