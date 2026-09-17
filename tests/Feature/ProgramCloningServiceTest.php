<?php

namespace Tests\Feature;

use App\Models\Exercise;
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
 * Fase 2 de docs/PLAN_CLONADO_PROGRAMAS.md — ProgramCloningService en
 * aislamiento (sin pasar por ningún endpoint HTTP ni por
 * ProgramAssignmentService). Verifica que clone() deja una copia real e
 * independiente en las 4 tablas involucradas, con el linaje
 * (`source_*_id`/`is_client_copy`) correcto, y que un WorkoutTemplate
 * compartido por varios días del programa se clona UNA sola vez.
 */
class ProgramCloningServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCoach(): User
    {
        return User::create([
            'first_name' => 'Coach',
            'last_name'  => 'Cloning',
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

    /**
     * Programa de 2 semanas, 3 días con entrenamiento (2 de ellos
     * compartiendo el MISMO WorkoutTemplate, día 1 de cada semana) y 1 día
     * de descanso (workout_template_id null).
     */
    private function makeLibraryProgram(): array
    {
        $coach = $this->makeCoach();
        $exercise = $this->makeExercise();

        $sharedTemplate = $this->makeWorkoutTemplate($coach, $exercise, 'Plantilla compartida ' . uniqid());
        $ownTemplate = $this->makeWorkoutTemplate($coach, $exercise, 'Plantilla exclusiva semana 1 ' . uniqid());

        $program = TrainingProgram::create([
            'title'     => 'Programa de biblioteca ' . uniqid(),
            'coach_id'  => $coach->id,
            'num_weeks' => 2,
            'activo'    => true,
        ]);

        // Semana 1, día 1: plantilla compartida.
        ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'          => 1,
            'day_of_week'          => 1,
            'workout_template_id'  => $sharedTemplate->id,
        ]);

        // Semana 1, día 2: plantilla exclusiva (no compartida).
        ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'          => 1,
            'day_of_week'          => 2,
            'workout_template_id'  => $ownTemplate->id,
        ]);

        // Semana 1, día 3: descanso.
        ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'          => 1,
            'day_of_week'          => 3,
            'workout_template_id'  => null,
        ]);

        // Semana 2, día 1: MISMA plantilla compartida que semana 1 día 1.
        ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'          => 2,
            'day_of_week'          => 1,
            'workout_template_id'  => $sharedTemplate->id,
        ]);

        return compact('coach', 'exercise', 'program', 'sharedTemplate', 'ownTemplate');
    }

    public function test_clone_creates_independent_rows_with_correct_lineage(): void
    {
        ['program' => $program, 'sharedTemplate' => $sharedTemplate, 'ownTemplate' => $ownTemplate] =
            $this->makeLibraryProgram();
        $client = $this->makeClient($program->coach_id);

        $clone = (new ProgramCloningService())->clone($program->fresh(), $client->id);

        // ── TrainingProgram ──────────────────────────────────────────
        $this->assertNotSame($program->id, $clone->id);
        $this->assertSame($program->id, $clone->source_training_program_id);
        $this->assertTrue($clone->is_client_copy);
        $this->assertSame($program->title, $clone->title);
        $this->assertSame($program->num_weeks, $clone->num_weeks);
        $this->assertSame($program->coach_id, $clone->coach_id);

        // ── ProgramDayAssignment: mismo número de días, training_program_id nuevo ──
        $clonedDays = ProgramDayAssignment::where('training_program_id', $clone->id)->orderBy('week_number')->orderBy('day_of_week')->get();
        $this->assertCount(4, $clonedDays);
        foreach ($clonedDays as $day) {
            $this->assertNotContains($day->id, $program->dayAssignments->pluck('id')->all());
        }

        // El día de descanso se clona como descanso (workout_template_id null).
        $restDay = $clonedDays->firstWhere('day_of_week', 3);
        $this->assertNull($restDay->workout_template_id);

        // ── WorkoutTemplate: 2 plantillas originales -> 2 clones, no 3 ──
        $clonedTemplateIds = $clonedDays->pluck('workout_template_id')->filter()->unique()->values();
        $this->assertCount(2, $clonedTemplateIds, 'La plantilla compartida por semana 1 y semana 2 debe clonarse UNA sola vez.');

        // El mismo WorkoutTemplate clon debe estar en semana1/día1 y semana2/día1.
        $week1Day1 = $clonedDays->first(fn ($d) => $d->week_number === 1 && $d->day_of_week === 1);
        $week2Day1 = $clonedDays->first(fn ($d) => $d->week_number === 2 && $d->day_of_week === 1);
        $this->assertSame($week1Day1->workout_template_id, $week2Day1->workout_template_id);
        $this->assertNotSame($sharedTemplate->id, $week1Day1->workout_template_id);

        $sharedClone = WorkoutTemplate::find($week1Day1->workout_template_id);
        $this->assertSame($sharedTemplate->id, $sharedClone->source_workout_template_id);
        $this->assertTrue($sharedClone->is_client_copy);
        $this->assertSame($sharedTemplate->title, $sharedClone->title);

        $week1Day2 = $clonedDays->first(fn ($d) => $d->week_number === 1 && $d->day_of_week === 2);
        $ownClone = WorkoutTemplate::find($week1Day2->workout_template_id);
        $this->assertSame($ownTemplate->id, $ownClone->source_workout_template_id);
        $this->assertNotSame($sharedClone->id, $ownClone->id);

        // ── WorkoutTemplateBlock + WorkoutTemplateExercise: filas propias, mismo contenido ──
        $originalBlock = $sharedTemplate->blocks->first();
        $clonedBlock = $sharedClone->blocks->first();
        $this->assertNotSame($originalBlock->id, $clonedBlock->id);
        $this->assertSame($originalBlock->title, $clonedBlock->title);
        $this->assertSame($originalBlock->instructions, $clonedBlock->instructions);

        $originalExercise = $originalBlock->exercises->first();
        $clonedExercise = $clonedBlock->exercises->first();
        $this->assertNotSame($originalExercise->id, $clonedExercise->id);
        $this->assertSame($originalExercise->exercise_id, $clonedExercise->exercise_id);
        $this->assertSame($originalExercise->prescribed, $clonedExercise->prescribed);
        $this->assertSame($originalExercise->enabled_metrics, $clonedExercise->enabled_metrics);
        $this->assertSame($originalExercise->notes, $clonedExercise->notes);

        // ── El original queda intacto ──────────────────────────────
        $this->assertDatabaseCount('training_programs', 2);
        $this->assertDatabaseCount('workout_templates', 4); // 2 originales + 2 clones
        $this->assertDatabaseCount('program_day_assignments', 8); // 4 originales + 4 clonados
    }

    public function test_clone_does_not_mutate_the_library_program(): void
    {
        ['program' => $program] = $this->makeLibraryProgram();
        $client = $this->makeClient($program->coach_id);

        $originalDayCount = $program->dayAssignments()->count();

        (new ProgramCloningService())->clone($program->fresh(), $client->id);

        $this->assertFalse($program->fresh()->is_client_copy);
        $this->assertNull($program->fresh()->source_training_program_id);
        $this->assertSame($originalDayCount, $program->dayAssignments()->count());
    }
}
