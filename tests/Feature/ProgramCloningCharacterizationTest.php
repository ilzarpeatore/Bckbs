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
use App\Services\MesocycleClosureService;
use App\Services\SessionProgressionRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TESTS DE CARACTERIZACIÓN -- PRE-clonado (Fase 0, ver
 * docs/PLAN_CLONADO_PROGRAMAS.md, sección 3 "Plan de migración por fases").
 *
 * Estos tests documentan el comportamiento ACTUAL del sistema, bug incluido
 * -- NO son una especificación de lo deseado. Todos deben PASAR hoy porque
 * `ProgramClientAssignment` es solo un pivote cliente<->training_program_id
 * (no clona nada, ver comentario literal "sin duplicar la plantilla" en su
 * propia migración): asignar el mismo `TrainingProgram` de biblioteca a dos
 * clientes distintos hace que ambos compartan la MISMA plantilla
 * (`WorkoutTemplate` + `ProgramDayAssignment`), así que cualquier mutación
 * de estructura hecha "para un cliente" (añadir/quitar ejercicio, añadir
 * bloque) es en realidad una mutación de la plantilla compartida, visible
 * para todos los clientes con ese programa Y para la biblioteca del coach.
 *
 * Tras ejecutar el plan completo (clonado real al asignar, Fases 1-7), los
 * tests de la sección 1 (aislamiento) DEBEN EMPEZAR A FALLAR -- es la señal
 * de que el bug se corrigió. Se reescriben entonces en Fase 4 (verificación
 * del Riesgo B) en vez de borrarse sin más. Los de la sección 2 (scope
 * `programa_especifico` del motor de progresión) documentan un
 * comportamiento a PRESERVAR funcionalmente después de la migración (ver
 * Riesgo A del plan) aunque cambie el mecanismo interno de resolución. El de
 * la sección 3 (cierre de mesociclo) es un snapshot de comportamiento ya
 * correcto, red de seguridad ante refactors accidentales del mismo dominio.
 */
class ProgramCloningCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Los endpoints de admin usados aquí (session-detail-*, training-program-
        // assign-client, client-calendar-import-program) cuelgan de
        // Route::prefix('admin')->middleware(['auth:sanctum','admin.api']) --
        // AdminApi exige $user->hasRole('admin') (spatie/permission). Mismo
        // patrón que ClientLimitationSeverityTest.
        Role::findOrCreate('admin', 'web');
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
        // coach_id no es mass-assignable (fillable de User no lo incluye) --
        // mismo patrón que ClientLimitationSeverityTest::makeClient().
        $client->forceFill(['coach_id' => $coachId])->save();

        return $client;
    }

    private function makeExercise(string $title = 'Ejercicio de prueba'): Exercise
    {
        return Exercise::create(['title' => $title, 'status' => 'active']);
    }

    /**
     * Construye un TrainingProgram de "biblioteca" (coach_id, sin client_id
     * -- exactamente el diseño post 2026_07_16_120001, plantilla reutilizable
     * sin dueño único) con UN día de calendario (semana 1, día 1) apuntando a
     * un WorkoutTemplate con un bloque y un ejercicio prescrito.
     *
     * @return array{program: TrainingProgram, template: WorkoutTemplate, block: WorkoutTemplateBlock, dayAssignment: ProgramDayAssignment, exercise: Exercise}
     */
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

    // ═══ 1. SessionDetailController: addExercise/addBlock/removeExercise ═══
    // mutan la plantilla compartida (foco exacto del bug, ver "Riesgo B" del
    // plan). Dos ProgramClientAssignment distintos apuntan al mismo
    // training_program_id -> comparten el mismo ProgramDayAssignment (cuelga
    // de training_program_id, no de la asignación por cliente) -> cualquier
    // cambio de estructura hecho desde la sesión de un cliente es visible
    // para el otro.

    public function test_add_exercise_on_client_a_session_is_seen_by_client_b(): void
    {
        $coach = $this->makeCoach();
        $clientA = $this->makeClient($coach->id);
        $clientB = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();
        $newExercise = $this->makeExercise('Ejercicio añadido por el coach para A');

        ['program' => $program, 'block' => $block, 'dayAssignment' => $dayAssignment] =
            $this->makeLibraryProgram($coach, $exercise);

        $this->assignClientDirectly($program, $clientA);
        $this->assignClientDirectly($program, $clientB);

        Sanctum::actingAs($coach, ['*']);

        // El coach añade un ejercicio "a la sesión de A" (program_day_assignment_id
        // es el único identificador que recibe el endpoint, sin client_id).
        $this->postJson('/api/admin/session-detail-add-exercise', [
            'program_day_assignment_id' => $dayAssignment->id,
            'workout_template_block_id'  => $block->id,
            'exercise_id'                => $newExercise->id,
        ])->assertStatus(200);

        // BUG ACTUAL: el cliente B, que corre el MISMO programa de biblioteca
        // pero nunca fue tocado directamente, ve igualmente el ejercicio
        // nuevo -- porque program_day_assignment_id resuelve siempre al
        // mismo workout_template_id compartido, sin importar client_id.
        $detail = $this->getJson('/api/admin/session-detail?' . http_build_query([
            'program_day_assignment_id' => $dayAssignment->id,
            'client_id'                  => $clientB->id,
        ]));
        $detail->assertStatus(200);

        $exerciseIdsSeenByB = collect($detail->json('data.blocks'))
            ->flatMap(fn ($b) => collect($b['exercises'])->pluck('exercise_id'));

        $this->assertTrue(
            $exerciseIdsSeenByB->contains($newExercise->id),
            'Comportamiento actual (bug): cliente B debería ver el ejercicio añadido para A, '
            . 'porque ambos comparten la misma plantilla (workout_template_id) tras asignar '
            . 'sin clonar. Si este assert falla, addExercise() ya está aislando por cliente.'
        );

        // Y la propia plantilla de biblioteca (sin pasar por ningún cliente)
        // también quedó mutada -- confirma que no hay copia de por medio.
        $this->assertDatabaseHas('workout_template_exercises', [
            'workout_template_block_id' => $block->id,
            'exercise_id'                => $newExercise->id,
        ]);
    }

    public function test_add_block_on_client_a_session_is_seen_by_client_b(): void
    {
        $coach = $this->makeCoach();
        $clientA = $this->makeClient($coach->id);
        $clientB = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();

        ['program' => $program, 'dayAssignment' => $dayAssignment] =
            $this->makeLibraryProgram($coach, $exercise);

        $this->assignClientDirectly($program, $clientA);
        $this->assignClientDirectly($program, $clientB);

        Sanctum::actingAs($coach, ['*']);

        $addBlockResponse = $this->postJson('/api/admin/session-detail-add-block', [
            'program_day_assignment_id' => $dayAssignment->id,
            'title'                      => 'Bloque nuevo de A',
        ]);
        $addBlockResponse->assertStatus(200);
        $newBlockId = $addBlockResponse->json('data.id');

        $detail = $this->getJson('/api/admin/session-detail?' . http_build_query([
            'program_day_assignment_id' => $dayAssignment->id,
            'client_id'                  => $clientB->id,
        ]));
        $detail->assertStatus(200);

        $blockIdsSeenByB = collect($detail->json('data.blocks'))->pluck('block_id');

        $this->assertTrue(
            $blockIdsSeenByB->contains($newBlockId),
            'Comportamiento actual (bug): el bloque añadido "para A" aparece también en la '
            . 'sesión de B, misma plantilla compartida.'
        );
    }

    public function test_remove_exercise_on_client_a_session_removes_it_for_client_b_too(): void
    {
        $coach = $this->makeCoach();
        $clientA = $this->makeClient($coach->id);
        $clientB = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();

        ['program' => $program, 'dayAssignment' => $dayAssignment, 'block' => $block] =
            $this->makeLibraryProgram($coach, $exercise);

        $this->assignClientDirectly($program, $clientA);
        $this->assignClientDirectly($program, $clientB);

        $templateExercise = WorkoutTemplateExercise::where('workout_template_block_id', $block->id)->firstOrFail();

        Sanctum::actingAs($coach, ['*']);

        $this->postJson('/api/admin/session-detail-remove-exercise', [
            'program_day_assignment_id'    => $dayAssignment->id,
            'workout_template_exercise_id' => $templateExercise->id,
        ])->assertStatus(200);

        $detail = $this->getJson('/api/admin/session-detail?' . http_build_query([
            'program_day_assignment_id' => $dayAssignment->id,
            'client_id'                  => $clientB->id,
        ]));
        $detail->assertStatus(200);

        $exerciseIdsSeenByB = collect($detail->json('data.blocks'))
            ->flatMap(fn ($b) => collect($b['exercises'])->pluck('exercise_id'));

        $this->assertFalse(
            $exerciseIdsSeenByB->contains($exercise->id),
            'Comportamiento actual (bug): quitar el ejercicio "de la sesión de A" lo hace '
            . 'desaparecer también para B, porque se borró la fila compartida '
            . 'workout_template_exercises, no una anulación por cliente.'
        );
        // WorkoutTemplateExercise usa SoftDeletes -- la fila sigue en la tabla
        // (assertDatabaseMissing no aplicaría), pero queda excluida por el
        // scope global por defecto, que es justo lo que getSessionDetail() ya
        // comprobó arriba.
        $this->assertSoftDeleted('workout_template_exercises', ['id' => $templateExercise->id]);
    }

    // ═══ 2. Puntos de entrada de asignación no clonan nada ═══════════════

    public function test_assign_client_endpoint_does_not_clone_the_training_program(): void
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

        // Sigue existiendo UNA sola fila de plantilla -- ningún clon nuevo.
        $this->assertDatabaseCount('training_programs', 1);

        $assignments = ProgramClientAssignment::where('client_id', $clientA->id)
            ->orWhere('client_id', $clientB->id)
            ->get();

        $this->assertCount(2, $assignments);
        foreach ($assignments as $assignment) {
            $this->assertSame(
                $program->id,
                $assignment->training_program_id,
                'Comportamiento actual (bug): ambas asignaciones apuntan al MISMO training_program_id de biblioteca.'
            );
        }
    }

    public function test_import_program_endpoint_does_not_clone_the_training_program(): void
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

        $this->assertDatabaseCount('training_programs', 1);

        $assignments = ProgramClientAssignment::where('client_id', $clientA->id)
            ->orWhere('client_id', $clientB->id)
            ->get();

        $this->assertCount(2, $assignments);
        foreach ($assignments as $assignment) {
            $this->assertSame(
                $program->id,
                $assignment->training_program_id,
                'Comportamiento actual (bug): ambas asignaciones (vía "importar programa") apuntan al MISMO training_program_id de biblioteca.'
            );
        }
    }

    // ═══ 3. Motor de progresión: scope programa_especifico matchea a
    // CUALQUIER cliente del programa (ScopeType::PROGRAMA_ESPECIFICO
    // documenta esto como comportamiento asumido, no accidental) ══════════

    public function test_programa_especifico_rule_applies_to_any_client_running_the_library_program(): void
    {
        $coach = $this->makeCoach();
        $clientA = $this->makeClient($coach->id);
        $clientB = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();
        ['program' => $program] = $this->makeLibraryProgram($coach, $exercise);

        $this->assignClientDirectly($program, $clientA);
        $this->assignClientDirectly($program, $clientB);

        $rule = SessionProgressionRule::create([
            'coach_id'          => $coach->id,
            'name'              => 'Regla de programa específico',
            'scope_type'        => ScopeType::PROGRAMA_ESPECIFICO->value,
            'scope_id'          => $program->id,
            'priority'          => 0,
            'active'            => true,
            'mode'              => RuleMode::AUTOMATICO->value,
            'fallback_behavior' => FallbackBehavior::MANTENER_SIN_CAMBIO->value,
            'shadow_mode'       => false,
        ]);

        $engine = new SessionProgressionRuleEngine();

        $rulesForA = $engine->applicableRules($clientA, $exercise->id, $program->id);
        $rulesForB = $engine->applicableRules($clientB, $exercise->id, $program->id);

        $this->assertTrue(
            $rulesForA->pluck('id')->contains($rule->id),
            'La regla programa_especifico debe aplicar al cliente A (corre ese training_program_id de biblioteca).'
        );
        $this->assertTrue(
            $rulesForB->pluck('id')->contains($rule->id),
            'Comportamiento actual (asumido, no bug -- ver ScopeType::PROGRAMA_ESPECIFICO): '
            . 'la MISMA regla, definida una sola vez sobre el id de biblioteca, aplica también '
            . 'a B porque ambos comparten training_program_id (nadie clona todavía). Tras el '
            . 'clonado, esto debe seguir cumpliéndose vía source_training_program_id (Riesgo A '
            . 'del plan), aunque el mecanismo interno cambie.'
        );
    }

    // ═══ 4. MesocycleClosureService: snapshot de comportamiento actual ═══

    public function test_close_eligible_assignments_marks_overdue_assignment_as_closed(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id);
        $exercise = $this->makeExercise();
        ['program' => $program] = $this->makeLibraryProgram($coach, $exercise);

        // fecha_fin ya pasada, activo=true, cerrado_at aún null -> elegible.
        $assignment = ProgramClientAssignment::create([
            'training_program_id' => $program->id,
            'client_id'            => $client->id,
            'start_date'           => now()->subWeeks(5)->toDateString(),
            'fecha_fin'            => now()->subDay()->toDateString(),
            'activo'               => true,
        ]);

        $count = (new MesocycleClosureService())->closeEligibleAssignments();

        $this->assertSame(1, $count);
        $this->assertNotNull(
            $assignment->fresh()->cerrado_at,
            'closeEligibleAssignments() debe marcar cerrado_at en toda asignación activa con fecha_fin superada, '
            . 'esté o no el cliente en paid-tier (el gate solo decide si se escribe el achievement_event).'
        );

        // Idempotencia del job: una segunda pasada el mismo día no debe volver a contarla.
        $secondCount = (new MesocycleClosureService())->closeEligibleAssignments();
        $this->assertSame(0, $secondCount);
    }
}
