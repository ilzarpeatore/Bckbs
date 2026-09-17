<?php

namespace Tests\Feature;

use App\Enums\FallbackBehavior;
use App\Enums\RuleMode;
use App\Enums\ScopeType;
use App\Models\Exercise;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\Role;
use App\Models\SessionProgressionRule;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use App\Services\SessionProgressionRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fase 3 de docs/PLAN_CLONADO_PROGRAMAS.md (Riesgo A): verifica que
 * `SessionProgressionRuleEngine::applicableRules()` sigue matcheando reglas
 * `scope_type=programa_especifico` tanto con el clonado desactivado (id
 * crudo, comportamiento actual preservado) como activado (el id resuelto de
 * la sesión es el CLON del cliente -- el motor debe resolver su
 * `source_training_program_id` y comparar contra ESE, no contra el id del
 * clon).
 *
 * Ver también `ProgramCloningCharacterizationTest::
 * test_programa_especifico_rule_applies_to_any_client_running_the_library_program`
 * (sección 3, flag OFF) -- ese archivo no se toca; el test 1 de aquí reutiliza
 * su misma lógica como red de seguridad de que el fix de Fase 3 no rompió el
 * caso ya correcto.
 */
class SessionProgressionProgramSpecificScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
    }

    private function makeCoach(): User
    {
        $coach = User::create([
            'first_name' => 'Coach',
            'last_name'  => 'Progression',
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
            'last_name'  => 'Progression',
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

    /** Mismo helper que ProgramCloningCharacterizationTest/ProgramCloningEnabledTest. */
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

    private function assignClientDirectly(TrainingProgram $program, User $client): ProgramClientAssignment
    {
        return ProgramClientAssignment::create([
            'training_program_id' => $program->id,
            'client_id'            => $client->id,
            'start_date'           => now()->toDateString(),
            'fecha_fin'            => now()->addWeeks($program->num_weeks)->toDateString(),
            'activo'               => true,
        ]);
    }

    private function makeProgramSpecificRule(int $coachId, int $scopeId, string $name = 'Regla de programa específico'): SessionProgressionRule
    {
        return SessionProgressionRule::create([
            'coach_id'          => $coachId,
            'name'              => $name,
            'scope_type'        => ScopeType::PROGRAMA_ESPECIFICO->value,
            'scope_id'          => $scopeId,
            'priority'          => 0,
            'active'            => true,
            'mode'              => RuleMode::AUTOMATICO->value,
            'fallback_behavior' => FallbackBehavior::MANTENER_SIN_CAMBIO->value,
            'shadow_mode'       => false,
        ]);
    }

    // ═══ 1. Flag desactivado (default): comportamiento actual preservado ═══

    public function test_flag_disabled_rule_applies_to_client_assigned_directly_to_library_program(): void
    {
        config(['services.program_cloning.enabled' => false]);

        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();
        ['program' => $program] = $this->makeLibraryProgram($coach, $exercise);

        // Sin clonado: la asignación apunta directo al id de biblioteca.
        $this->assignClientDirectly($program, $client);

        $rule = $this->makeProgramSpecificRule($coach->id, $program->id);

        $engine = new SessionProgressionRuleEngine();
        $rules = $engine->applicableRules($client, $exercise->id, $program->id);

        $this->assertTrue(
            $rules->pluck('id')->contains($rule->id),
            'Con el clonado desactivado, la regla programa_especifico debe seguir aplicando '
            . 'comparando el id de biblioteca directamente (comportamiento preexistente).'
        );
    }

    // ═══ 2. Flag activado: matchea vía source_training_program_id del clon ═══

    public function test_flag_enabled_rule_applies_to_both_clients_via_their_own_clone(): void
    {
        config(['services.program_cloning.enabled' => true]);

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

        $assignmentA = ProgramClientAssignment::where('client_id', $clientA->id)->firstOrFail();
        $assignmentB = ProgramClientAssignment::where('client_id', $clientB->id)->firstOrFail();

        // Confirma la premisa del test: cada cliente tiene su PROPIO clon,
        // distinto entre sí y del id de biblioteca.
        $this->assertNotSame($assignmentA->training_program_id, $assignmentB->training_program_id);
        $this->assertNotSame($program->id, $assignmentA->training_program_id);
        $this->assertSame($program->id, TrainingProgram::find($assignmentA->training_program_id)->source_training_program_id);
        $this->assertSame($program->id, TrainingProgram::find($assignmentB->training_program_id)->source_training_program_id);

        // La regla se crea sobre el id de BIBLIOTECA, tal como la elige el coach.
        $rule = $this->makeProgramSpecificRule($coach->id, $program->id);

        $engine = new SessionProgressionRuleEngine();

        // $trainingProgramId pasado aquí es el que resolveTrainingProgramIdForSession()
        // devolvería en producción: el id del CLON de cada cliente, no el de biblioteca.
        $rulesForA = $engine->applicableRules($clientA, $exercise->id, $assignmentA->training_program_id);
        $rulesForB = $engine->applicableRules($clientB, $exercise->id, $assignmentB->training_program_id);

        $this->assertTrue(
            $rulesForA->pluck('id')->contains($rule->id),
            'Con el clonado activo, la regla programa_especifico (definida sobre el id de '
            . 'biblioteca) debe seguir aplicando al cliente A resolviendo su clon -> origen.'
        );
        $this->assertTrue(
            $rulesForB->pluck('id')->contains($rule->id),
            'Mismo caso para el cliente B, con su propio clon distinto del de A.'
        );
    }

    // ═══ 3. Caso negativo: no matchea todo, solo el programa de origen correcto ═══

    public function test_flag_enabled_rule_does_not_match_a_different_library_program(): void
    {
        config(['services.program_cloning.enabled' => true]);

        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();
        ['program' => $program] = $this->makeLibraryProgram($coach, $exercise);
        ['program' => $otherProgram] = $this->makeLibraryProgram($coach, $exercise);

        Sanctum::actingAs($coach, ['*']);

        $this->postJson('/api/admin/training-program-assign-client', [
            'training_program_id' => $program->id,
            'client_id'            => $client->id,
            'start_date'           => now()->toDateString(),
        ])->assertStatus(200);

        $assignment = ProgramClientAssignment::where('client_id', $client->id)->firstOrFail();

        // La regla apunta a un programa de biblioteca DISTINTO al asignado.
        $rule = $this->makeProgramSpecificRule($coach->id, $otherProgram->id, 'Regla de otro programa');

        $engine = new SessionProgressionRuleEngine();
        $rules = $engine->applicableRules($client, $exercise->id, $assignment->training_program_id);

        $this->assertFalse(
            $rules->pluck('id')->contains($rule->id),
            'Una regla programa_especifico sobre un programa de biblioteca distinto no debe '
            . 'matchear -- confirma que la resolución clon->origen no volvió el scope "matchea todo".'
        );
    }
}
