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
 * Fase 5 de docs/PLAN_CLONADO_PROGRAMAS.md — con `PROGRAM_CLONING_ENABLED`
 * activo, `ProgramCloningService` deja tras cada asignación un
 * `TrainingProgram`/`WorkoutTemplate` con `is_client_copy=true`. Estos
 * tests confirman que esas copias NUNCA aparecen en los listados de
 * biblioteca reutilizable (`WorkoutTemplateController::getList` /
 * `TrainingProgramController::getList`), pero que las plantillas/programas
 * de biblioteca normales -- incluida la plantilla que se acaba de clonar --
 * siguen viéndose ahí sin cambios.
 *
 * Reutiliza los mismos helpers que `ProgramCloningEnabledTest`.
 */
class LibraryListingsExcludeClientCopiesTest extends TestCase
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
            'last_name'  => 'Listings',
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
            'last_name'  => 'Listings',
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

    /** Mismo helper que ProgramCloningEnabledTest::makeLibraryProgram(). */
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

    public function test_workout_template_list_excludes_client_copies_but_keeps_library_templates(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();
        ['program' => $program, 'template' => $libraryTemplate] = $this->makeLibraryProgram($coach, $exercise);

        // Plantilla suelta normal del coach (sin días de programa asociados):
        // debe seguir apareciendo, el filtro nuevo no debe tocarla.
        $looseTemplate = WorkoutTemplate::create([
            'coach_id' => $coach->id,
            'title'    => 'Plantilla suelta reutilizable ' . uniqid(),
        ]);

        Sanctum::actingAs($coach, ['*']);

        $this->postJson('/api/admin/training-program-assign-client', [
            'training_program_id' => $program->id,
            'client_id'            => $client->id,
            'start_date'           => now()->toDateString(),
        ])->assertStatus(200);

        $assignment = ProgramClientAssignment::where('client_id', $client->id)->firstOrFail();
        $dayAssignment = ProgramDayAssignment::where('training_program_id', $assignment->training_program_id)->firstOrFail();
        $clonedTemplateId = $dayAssignment->workout_template_id;

        $clonedTemplate = WorkoutTemplate::find($clonedTemplateId);
        $this->assertNotNull($clonedTemplate);
        $this->assertTrue($clonedTemplate->is_client_copy);
        $this->assertNotSame($libraryTemplate->id, $clonedTemplateId);

        // getList() no soporta per_page=-1 (solo lo hace TrainingProgramController::getList) --
        // el default (100) es de sobra para las 2-3 plantillas de este test.
        $response = $this->getJson('/api/admin/workout-template-list')->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse(
            $ids->contains($clonedTemplateId),
            'El WorkoutTemplate clonado para el cliente no debe aparecer en el listado de biblioteca.'
        );
        // La plantilla original del programa de biblioteca SÍ tiene
        // programDayAssignments (la del propio $program), así que el
        // filtro whereDoesntHave('programDayAssignments') ya la excluía de
        // antes -- eso no cambia con esta fase. Se confirma igualmente que
        // sigue sin aparecer (comportamiento previo, no regresión nueva).
        $this->assertFalse($ids->contains($libraryTemplate->id));
        // La plantilla suelta reutilizable sí debe seguir apareciendo.
        $this->assertTrue(
            $ids->contains($looseTemplate->id),
            'Una plantilla suelta normal (sin programDayAssignments, is_client_copy=false) debe seguir en el listado.'
        );
    }

    public function test_training_program_list_excludes_client_copies_but_keeps_the_library_program(): void
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

        $assignment = ProgramClientAssignment::where('client_id', $client->id)->firstOrFail();
        $clonedProgramId = $assignment->training_program_id;

        $clonedProgram = TrainingProgram::find($clonedProgramId);
        $this->assertNotNull($clonedProgram);
        $this->assertTrue($clonedProgram->is_client_copy);
        $this->assertNotSame($program->id, $clonedProgramId);

        $response = $this->getJson('/api/admin/training-program-list?per_page=-1')->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse(
            $ids->contains($clonedProgramId),
            'El TrainingProgram clonado para el cliente no debe aparecer en el listado de biblioteca.'
        );
        $this->assertTrue(
            $ids->contains($program->id),
            'El programa de biblioteca original debe seguir apareciendo en el listado.'
        );
    }
}
