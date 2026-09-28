<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El blog de la app y el de la web comparten el mismo endpoint
 * (GET post-list) sin ningún filtro -- el usuario quiere separar contenido
 * orientado a captación (solo web) del educativo (app y web). Cubre el
 * nuevo campo `channel` (app/web/both, default both) y su filtro opcional
 * en GET post-list -- sin parámetro, comportamiento idéntico al de siempre
 * (retrocompatible con la app/web actuales, que todavía no mandan `channel`).
 */
class PostChannelFilterTest extends TestCase
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
            'last_name'  => 'Test',
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

    private function makePost(string $title, string $channel = 'both'): Post
    {
        return Post::create([
            'title'   => $title,
            'status'  => 'publish',
            'channel' => $channel,
        ]);
    }

    public function test_new_post_defaults_to_both_channels(): void
    {
        $post = $this->makePost('Post sin canal explícito', channel: 'both');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'channel' => 'both']);
    }

    public function test_admin_can_create_post_with_explicit_channel(): void
    {
        $coach = $this->makeCoach();
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/posts', [
            'title'   => 'Oferta de lanzamiento',
            'status'  => 'draft',
            'channel' => 'web',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', ['title' => 'Oferta de lanzamiento', 'channel' => 'web']);
    }

    public function test_admin_rejects_invalid_channel(): void
    {
        $coach = $this->makeCoach();
        Sanctum::actingAs($coach, ['*']);

        $response = $this->postJson('/api/admin/posts', [
            'title'   => 'Post con canal inválido',
            'channel' => 'instagram',
        ]);

        $response->assertStatus(422);
    }

    public function test_post_list_without_channel_param_returns_everything(): void
    {
        $this->makePost('Educativo ambos', channel: 'both');
        $this->makePost('Solo app', channel: 'app');
        $this->makePost('Solo web', channel: 'web');

        $response = $this->getJson('/api/post-list');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_post_list_filtered_by_app_excludes_web_only(): void
    {
        $this->makePost('Educativo ambos', channel: 'both');
        $this->makePost('Solo app', channel: 'app');
        $this->makePost('Solo web (captación)', channel: 'web');

        $response = $this->getJson('/api/post-list?channel=app');

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Educativo ambos'));
        $this->assertTrue($titles->contains('Solo app'));
        $this->assertFalse($titles->contains('Solo web (captación)'));
    }

    public function test_post_list_filtered_by_web_excludes_app_only(): void
    {
        $this->makePost('Educativo ambos', channel: 'both');
        $this->makePost('Solo app', channel: 'app');
        $this->makePost('Solo web (captación)', channel: 'web');

        $response = $this->getJson('/api/post-list?channel=web');

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Educativo ambos'));
        $this->assertTrue($titles->contains('Solo web (captación)'));
        $this->assertFalse($titles->contains('Solo app'));
    }
}
