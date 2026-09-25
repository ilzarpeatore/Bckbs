<?php

namespace Tests\Feature;

use App\Models\DailyReadinessCheck;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormAssignment;
use App\Models\FormQuestion;
use App\Models\FormSubmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Historial de check-ins del propio cliente (Check-ins > Historial de la app):
 * readiness diario y formularios enviados. Siempre solo lo propio.
 */
class CheckInHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
        $this->me = $this->makeUser();
        $this->other = $this->makeUser();
    }

    private function makeUser(): User
    {
        $u = User::create([
            'first_name' => 'Test', 'last_name' => 'User', 'username' => 'u_'.uniqid(),
            'email' => uniqid().'@example.test', 'password' => bcrypt('p'),
            'user_type' => 'user', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $u->assignRole('user');

        return $u;
    }

    private function readiness(User $u, string $date, int $sleep): void
    {
        DailyReadinessCheck::create(['user_id' => $u->id, 'date' => $date, 'sleep_quality' => $sleep, 'soreness_level' => 2, 'energy_level' => 3, 'stress_level' => 1]);
    }

    /** Envío de un check-in de $u con una pregunta y una respuesta. */
    private function submission(User $u, string $title, string $answer): FormSubmission
    {
        $form = Form::create(['coach_id' => $this->other->id, 'title' => $title, 'recurrence' => 'weekly']);
        $question = FormQuestion::create(['form_id' => $form->id, 'question_text' => '¿Cómo te has sentido?', 'type' => 'text', 'order' => 1]);
        $assignment = FormAssignment::create(['form_id' => $form->id, 'client_id' => $u->id, 'active' => true]);
        $submission = FormSubmission::create(['form_assignment_id' => $assignment->id, 'submitted_at' => now()]);
        FormAnswer::create(['form_submission_id' => $submission->id, 'form_question_id' => $question->id, 'answer_value' => $answer]);

        return $submission;
    }

    public function test_readiness_history_returns_only_my_checks_newest_first(): void
    {
        $this->readiness($this->me, '2026-09-20', 4);
        $this->readiness($this->me, '2026-09-22', 2);
        $this->readiness($this->other, '2026-09-21', 5);

        Sanctum::actingAs($this->me);
        $res = $this->getJson('/api/v1/readiness-history')->assertOk();

        $this->assertSame(['2026-09-22', '2026-09-20'], collect($res->json('data'))->pluck('date')->all());
        $this->assertSame([2, 4], collect($res->json('data'))->pluck('sleep_quality')->all());
    }

    public function test_readiness_history_limit_is_validated_and_applied(): void
    {
        foreach (['2026-09-18', '2026-09-19', '2026-09-20'] as $d) {
            $this->readiness($this->me, $d, 3);
        }
        Sanctum::actingAs($this->me);

        $this->assertCount(2, $this->getJson('/api/v1/readiness-history?limit=2')->assertOk()->json('data'));
        $this->getJson('/api/v1/readiness-history?limit=9999')->assertStatus(422);
    }

    public function test_my_submissions_lists_only_mine(): void
    {
        $mine = $this->submission($this->me, 'Check-in semanal', 'Bien');
        $this->submission($this->other, 'Check-in de otro', 'Mal');

        Sanctum::actingAs($this->me);
        $res = $this->getJson('/api/form-my-submissions')->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame($mine->id, $res->json('data.0.id'));
        $this->assertSame('Check-in semanal', $res->json('data.0.form_title'));
        $this->assertSame(1, $res->json('data.0.answers_count'));
    }

    public function test_submission_detail_shows_my_answers_and_hides_other_peoples(): void
    {
        $mine = $this->submission($this->me, 'Check-in semanal', 'Bien');
        $theirs = $this->submission($this->other, 'Check-in de otro', 'Mal');

        Sanctum::actingAs($this->me);
        $this->getJson('/api/form-submission-detail?id='.$mine->id)
            ->assertOk()
            ->assertJsonPath('data.form_title', 'Check-in semanal')
            ->assertJsonPath('data.answers.0.question', '¿Cómo te has sentido?')
            ->assertJsonPath('data.answers.0.answer', 'Bien');

        // Un envío ajeno responde igual que uno inexistente: 404, sin revelar que existe.
        $this->getJson('/api/form-submission-detail?id='.$theirs->id)->assertStatus(404);
        $this->getJson('/api/form-submission-detail?id=999999')->assertStatus(404);
    }
}
