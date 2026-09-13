<?php

namespace Tests\Feature\Security;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Posting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression tests for the IDOR sweep of 2026-09-13.
 *
 * Covers the two already-fixed unauthenticated endpoints (GET /user-detail,
 * GET /client/subscription) plus the new missing-ownership bugs found in
 * this pass (Comment::scopeMyComment(), CommentReplyController::saveCommentReply(),
 * FormController::leaveFeedback()) -- all follow the same shape: a caller
 * that trusts an id/user_id/client_id read from the request instead of the
 * authenticated identity.
 */
class IdorRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // hasRole('user') gates the ownership filter in Comment/CommentReply
        // scopes (see app/Models/Comment.php) -- without the role assigned,
        // real registration (UserController::register) always calls
        // assignRole($input['user_type']), so tests must mirror that.
        Role::findOrCreate('user', 'web');
        Role::findOrCreate('admin', 'web');
    }

    private function makeUser(array $attributes = []): User
    {
        // coach_id / email_verified_at aren't mass-assignable (see User::$fillable) --
        // set them with forceFill so tests can still seed a coach relationship.
        $forced = array_intersect_key($attributes, array_flip(['coach_id']));
        $attributes = array_diff_key($attributes, $forced);

        $user = User::create(array_merge([
            'first_name'    => 'Test',
            'last_name'     => 'User',
            'username'      => 'user_' . uniqid(),
            'email'         => uniqid() . '@example.test',
            'password'      => bcrypt('password'),
            'user_type'     => 'user',
            'status'        => 'active',
            'login_type'    => 'manual',
        ], $attributes));

        if ($forced) {
            $user->forceFill($forced)->save();
        }

        $user->assignRole('user');

        return $user;
    }

    // ═══ GET /api/user-detail ══════════════════════════════════════════

    public function test_user_detail_requires_authentication(): void
    {
        $this->getJson('/api/user-detail')->assertStatus(401);
    }

    public function test_user_detail_never_returns_another_users_data(): void
    {
        $victim = $this->makeUser(['email' => 'victim@example.test']);
        $attacker = $this->makeUser(['email' => 'attacker@example.test']);

        Sanctum::actingAs($attacker, ['*']);

        // Every shape the old vulnerable code trusted: ?id=, ?user_id=.
        foreach (['id', 'user_id'] as $param) {
            $response = $this->getJson('/api/user-detail?' . $param . '=' . $victim->id);
            $response->assertStatus(200);
            $response->assertJsonPath('data.id', $attacker->id);
            $response->assertJsonPath('data.email', $attacker->email);
            $this->assertNotSame($victim->email, $response->json('data.email'));
        }
    }

    // ═══ GET /api/client/subscription ═══════════════════════════════════

    public function test_client_subscription_requires_authentication(): void
    {
        $this->getJson('/api/client/subscription')->assertStatus(401);
    }

    public function test_client_subscription_never_returns_another_users_subscription(): void
    {
        $victim = $this->makeUser(['email' => 'victim2@example.test']);
        $attacker = $this->makeUser(['email' => 'attacker2@example.test']);

        $planId = DB::table('plans')->insertGetId([
            'name'  => 'Pro Plan',
            'slug'  => 'pro-plan-' . uniqid(),
            'price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Only the VICTIM has a real subscription row.
        DB::table('plan_subscriptions')->insert([
            'subscriber_type' => User::class,
            'subscriber_id'   => $victim->id,
            'plan_id'         => $planId,
            'name'            => 'Pro Plan',
            'slug'            => 'sub-' . uniqid(),
            'starts_at'       => now()->subDay(),
            'ends_at'         => now()->addMonth(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        Sanctum::actingAs($attacker, ['*']);

        foreach (['client_id', 'user_id', 'id'] as $param) {
            $response = $this->getJson('/api/client/subscription?' . $param . '=' . $victim->id);
            $response->assertStatus(200);
            // The attacker has no subscription of their own -> must see null,
            // never the victim's plan.
            $response->assertJsonPath('data', null);
        }
    }

    // ═══ POST /api/update-comment (Comment::scopeMyComment IDOR) ═══════

    public function test_update_comment_cannot_edit_another_users_comment_via_user_id_param(): void
    {
        $victim = $this->makeUser(['email' => 'victim3@example.test']);
        $attacker = $this->makeUser(['email' => 'attacker3@example.test']);

        $posting = Posting::create([
            'user_id'     => $victim->id,
            'description' => 'A post',
            'status'      => 'published',
        ]);

        $comment = Comment::create([
            'user_id'    => $victim->id,
            'posting_id' => $posting->id,
            'comment'    => 'original text',
        ]);

        Sanctum::actingAs($attacker, ['*']);

        // Old bug: scopeMyComment() trusted request('user_id') and matched
        // the victim's own comment when the attacker supplied it explicitly.
        $response = $this->postJson('/api/update-comment', [
            'id'         => $comment->id,
            'posting_id' => $posting->id,
            'user_id'    => $victim->id,
            'comment'    => 'hijacked by attacker',
        ]);

        $response->assertStatus(400);
        $this->assertSame('original text', $comment->fresh()->comment);
        $this->assertSame($victim->id, $comment->fresh()->user_id);
    }

    public function test_update_comment_can_edit_own_comment(): void
    {
        $owner = $this->makeUser(['email' => 'owner@example.test']);

        $posting = Posting::create([
            'user_id'     => $owner->id,
            'description' => 'A post',
            'status'      => 'published',
        ]);

        $comment = Comment::create([
            'user_id'    => $owner->id,
            'posting_id' => $posting->id,
            'comment'    => 'original text',
        ]);

        Sanctum::actingAs($owner, ['*']);

        $response = $this->postJson('/api/update-comment', [
            'id'         => $comment->id,
            'posting_id' => $posting->id,
            'comment'    => 'edited by owner',
        ]);

        $response->assertStatus(200);
        $this->assertSame('edited by owner', $comment->fresh()->comment);
    }

    // ═══ POST /api/save-comment-reply (missing ownership check) ═══════

    public function test_save_comment_reply_cannot_edit_another_users_reply(): void
    {
        $victim = $this->makeUser(['email' => 'victim4@example.test']);
        $attacker = $this->makeUser(['email' => 'attacker4@example.test']);

        $posting = Posting::create([
            'user_id'     => $victim->id,
            'description' => 'A post',
            'status'      => 'published',
        ]);

        $comment = Comment::create([
            'user_id'    => $victim->id,
            'posting_id' => $posting->id,
            'comment'    => 'root comment',
        ]);

        $reply = CommentReply::create([
            'user_id'    => $victim->id,
            'comment_id' => $comment->id,
            'comment'    => 'original reply',
        ]);

        Sanctum::actingAs($attacker, ['*']);

        $response = $this->postJson('/api/save-comment-reply', [
            'id'         => $reply->id,
            'comment_id' => $comment->id,
            'comment'    => 'hijacked reply',
        ]);

        // Not found for this caller (scoped to their own replies) -- not a 500,
        // and definitely not a successful edit of someone else's reply.
        $response->assertStatus(400);
        $this->assertSame('original reply', $reply->fresh()->comment);
        $this->assertSame($victim->id, $reply->fresh()->user_id);
    }

    public function test_save_comment_reply_can_edit_own_reply(): void
    {
        $owner = $this->makeUser(['email' => 'owner2@example.test']);

        $posting = Posting::create([
            'user_id'     => $owner->id,
            'description' => 'A post',
            'status'      => 'published',
        ]);

        $comment = Comment::create([
            'user_id'    => $owner->id,
            'posting_id' => $posting->id,
            'comment'    => 'root comment',
        ]);

        $reply = CommentReply::create([
            'user_id'    => $owner->id,
            'comment_id' => $comment->id,
            'comment'    => 'original reply',
        ]);

        Sanctum::actingAs($owner, ['*']);

        $response = $this->postJson('/api/save-comment-reply', [
            'id'         => $reply->id,
            'comment_id' => $comment->id,
            'comment'    => 'edited by owner',
        ]);

        $response->assertStatus(200);
        $this->assertSame('edited by owner', $reply->fresh()->comment);
    }

    // ═══ POST /api/form-feedback (missing coach-ownership check) ═══════

    public function test_form_feedback_requires_being_the_clients_coach(): void
    {
        $coach = $this->makeUser(['email' => 'coach@example.test', 'user_type' => 'coach']);
        $client = $this->makeUser(['email' => 'client@example.test', 'coach_id' => $coach->id]);
        $stranger = $this->makeUser(['email' => 'stranger@example.test']);

        $form = Form::create(['coach_id' => $coach->id, 'title' => 'Weekly Check-in']);
        $assignment = FormAssignment::create(['form_id' => $form->id, 'client_id' => $client->id, 'active' => true]);
        $submission = FormSubmission::create(['form_assignment_id' => $assignment->id, 'submitted_at' => now()]);

        // A random authenticated user (not the client's coach, not an admin)
        // must not be able to leave feedback on someone else's submission.
        Sanctum::actingAs($stranger, ['*']);
        $response = $this->postJson('/api/form-feedback', [
            'submission_id'  => $submission->id,
            'coach_feedback' => 'attacker feedback',
        ]);
        $response->assertStatus(403);
        $this->assertNull($submission->fresh()->coach_feedback);

        // The client's real coach can.
        Sanctum::actingAs($coach, ['*']);
        $response = $this->postJson('/api/form-feedback', [
            'submission_id'  => $submission->id,
            'coach_feedback' => 'great job this week',
        ]);
        $response->assertStatus(200);
        $this->assertSame('great job this week', $submission->fresh()->coach_feedback);
    }
}
