<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormAssignment;
use App\Models\FormQuestion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fotos de progreso del propio cliente desde la app (antes/después) y fotos
 * subidas desde una pregunta de check-in. Siempre solo lo propio y siempre
 * con URL firmada (disco 'private').
 */
class MyProgressPhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
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

    private function upload(User $u, array $extra = [])
    {
        Sanctum::actingAs($u);

        return $this->post('/api/v1/my-progress-photo-store', array_merge([
            'photo' => UploadedFile::fake()->image('p.jpg', 600, 800),
        ], $extra), ['Accept' => 'application/json']);
    }

    public function test_store_saves_pose_and_date_and_returns_signed_url(): void
    {
        $res = $this->upload($this->me, ['pose' => 'side', 'taken_at' => '2026-09-01']);

        $res->assertStatus(201)
            ->assertJsonPath('data.pose', 'side')
            ->assertJsonPath('data.taken_at', '2026-09-01');
        $this->assertStringContainsString('signature=', $res->json('data.url'));
        $this->assertCount(1, $this->me->fresh()->getMedia('progress_photos'));

        $this->get($res->json('data.url'))->assertOk();
    }

    public function test_invalid_pose_is_rejected_and_missing_pose_is_other(): void
    {
        $this->upload($this->me, ['pose' => 'selfie'])->assertStatus(422);
        $this->upload($this->me)->assertStatus(201)->assertJsonPath('data.pose', 'other');
    }

    public function test_list_only_returns_my_photos_newest_first(): void
    {
        $this->upload($this->me, ['pose' => 'front', 'taken_at' => '2026-08-01']);
        $this->upload($this->me, ['pose' => 'front', 'taken_at' => '2026-09-15']);
        $this->upload($this->other, ['pose' => 'front']);

        Sanctum::actingAs($this->me);
        $res = $this->getJson('/api/v1/my-progress-photos')->assertOk();

        $this->assertSame(['2026-09-15', '2026-08-01'], array_column($res->json('data'), 'taken_at'));
    }

    public function test_cannot_delete_someone_elses_photo(): void
    {
        $theirs = $this->upload($this->other)->json('data.id');

        Sanctum::actingAs($this->me);
        $this->postJson('/api/v1/my-progress-photo-delete', ['photo_id' => $theirs])->assertStatus(404);
        $this->assertCount(1, $this->other->fresh()->getMedia('progress_photos'));

        Sanctum::actingAs($this->other);
        $this->postJson('/api/v1/my-progress-photo-delete', ['photo_id' => $theirs])->assertOk();
        $this->assertCount(0, $this->other->fresh()->getMedia('progress_photos'));
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v1/my-progress-photos')->assertStatus(401);
    }

    public function test_checkin_photos_go_to_gallery_with_pose_and_answer_is_signed_on_read(): void
    {
        $form = Form::create(['coach_id' => $this->other->id, 'title' => 'Check-in']);
        $q = FormQuestion::create([
            'form_id' => $form->id, 'question_text' => 'Fotos', 'type' => 'progress_photos',
            'order' => 1, 'is_required' => false, 'max_files' => 3,
        ]);
        $assignment = FormAssignment::create(['form_id' => $form->id, 'client_id' => $this->me->id, 'active' => true]);

        Sanctum::actingAs($this->me);
        $this->post('/api/form-submit', [
            'form_assignment_id' => $assignment->id,
            'answers' => [['form_question_id' => $q->id, 'answer_value' => null]],
            "media_{$q->id}" => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            "poses_{$q->id}" => ['front', 'back'],
        ], ['Accept' => 'application/json'])->assertOk();

        $media = $this->me->fresh()->getMedia('progress_photos');
        $this->assertSame(['front', 'back'], $media->map->getCustomProperty('pose')->values()->all());

        $answer = FormAnswer::where('form_question_id', $q->id)->firstOrFail();
        $this->assertStringStartsWith('["progress-photo:', $answer->getRawOriginal('answer_value'));
        $urls = json_decode($answer->answer_value, true);
        $this->assertCount(2, $urls);
        $this->assertStringContainsString('signature=', $urls[0]);
        $this->get($urls[0])->assertOk();
    }
}
