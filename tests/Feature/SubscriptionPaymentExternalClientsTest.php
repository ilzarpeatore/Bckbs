<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SubscriptionPaymentClient;
use App\Models\SubscriptionPaymentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Seguimiento de pagos con clientes "externos" (sin cuenta en la app, p. ej.
 * los que solo estaban en Notion): alta, listado junto a los usuarios,
 * marcar meses, resumen y borrado.
 */
class SubscriptionPaymentExternalClientsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        $this->admin = User::create([
            'first_name' => 'Coach',
            'last_name'  => 'Test',
            'username'   => 'coach_' . uniqid(),
            'email'      => uniqid() . '@example.test',
            'password'   => bcrypt('password'),
            'user_type'  => 'coach',
            'status'     => 'active',
            'login_type' => 'manual',
        ]);
        $this->admin->assignRole('admin');
        Sanctum::actingAs($this->admin, ['*']);

        // La migración 2026_10_02_190000_import_notion_payments_2026 siembra
        // los clientes de Notion; estos tests parten de una tabla vacía.
        SubscriptionPaymentRecord::query()->delete();
        SubscriptionPaymentClient::query()->delete();
    }

    public function test_external_client_lifecycle(): void
    {
        $user = User::create([
            'first_name' => 'Zoe',
            'last_name'  => 'Registrada',
            'username'   => 'client_' . uniqid(),
            'email'      => uniqid() . '@example.test',
            'password'   => bcrypt('password'),
            'user_type'  => 'user',
            'status'     => 'active',
            'login_type' => 'manual',
        ]);
        $user->forceFill(['is_personal_client' => true, 'monthly_fee' => 50])->save();
        $this->putJson("/api/admin/subscription-payments/{$user->id}/2026/2", ['paid' => true, 'amount' => 50])->assertStatus(200);

        $created = $this->postJson('/api/admin/subscription-payments/external', [
            'name' => 'Borja Betanzos',
            'monthly_fee' => 55,
            'notes' => 'Importado de Notion',
        ])->assertStatus(201)->assertJsonPath('data.source', 'external');
        $externalId = $created->json('data.id');

        $this->putJson("/api/admin/subscription-payments/external/{$externalId}/2026/1", [
            'paid' => true, 'amount' => 23, 'paid_at' => '2026-01-01',
        ])->assertStatus(200)->assertJsonPath('data.external_client_id', $externalId);
        // Repetir el mismo mes actualiza, no duplica.
        $this->putJson("/api/admin/subscription-payments/external/{$externalId}/2026/1", [
            'paid' => true, 'amount' => 23,
        ])->assertStatus(200);
        $this->assertSame(1, SubscriptionPaymentRecord::where('external_client_id', $externalId)->count());

        $list = $this->getJson('/api/admin/subscription-payments?year=2026')->assertStatus(200);
        $rows = collect($list->json('data.clients'));
        $this->assertCount(2, $rows);
        $external = $rows->firstWhere('source', 'external');
        $this->assertSame('Borja Betanzos', $external['name']);
        $this->assertTrue($external['months'][1]['paid']);
        $this->assertEquals(23, $external['months'][1]['amount']);
        $this->assertEquals(55, $external['months'][2]['amount']);
        $this->assertSame('user', $rows->firstWhere('id', $user->id)['source']);

        $summary = $this->getJson('/api/admin/subscription-payments/summary?year=2026')->assertStatus(200);
        $summary->assertJsonPath('data.total_clients', 2);
        $this->assertEquals(73, $summary->json('data.total_year'));

        $this->putJson("/api/admin/subscription-payments/external/{$externalId}", ['monthly_fee' => 60])
            ->assertStatus(200)->assertJsonPath('data.monthly_fee', 60);

        $this->deleteJson("/api/admin/subscription-payments/external/{$externalId}")->assertStatus(200);
        $this->assertNull(SubscriptionPaymentClient::find($externalId));
        $this->assertSame(0, SubscriptionPaymentRecord::where('external_client_id', $externalId)->count());
    }

    public function test_external_client_requires_name(): void
    {
        $this->postJson('/api/admin/subscription-payments/external', ['monthly_fee' => 10])->assertStatus(422);
    }

    private function makeUser(string $first, string $last, bool $personal = false): User
    {
        $user = User::create([
            'first_name' => $first,
            'last_name'  => $last,
            'username'   => 'client_' . uniqid(),
            'email'      => uniqid() . '@example.test',
            'password'   => bcrypt('password'),
            'user_type'  => 'user',
            'status'     => 'active',
            'login_type' => 'manual',
        ]);
        $user->forceFill(['is_personal_client' => $personal])->save();

        return $user;
    }

    public function test_name_score_tolerates_typos_and_nicknames(): void
    {
        $score = fn ($a, $b) => \App\Http\Controllers\API\Admin\SubscriptionPaymentController::nameScore($a, $b);
        $this->assertGreaterThan(0, $score('Hamza Bilbao', 'Hamsa Dris Bakkali'));
        $this->assertGreaterThan(0, $score('Toni Perez', 'Antonio Pérez'));
        $this->assertGreaterThan(0, $score('Nerea TEAM', 'Nerea Gómez'));
        $this->assertSame(3, $score('Borja Betanzos', 'Borja Betanzos'));
        $this->assertSame(0, $score('Borja Betanzos', 'Lucía Vidal'));
    }

    public function test_merge_candidates_and_merge_into_real_user(): void
    {
        $hamsa = $this->makeUser('Hamsa', 'Dris Bakkali');
        $this->makeUser('Lucía', 'Vidal');

        $externalId = $this->postJson('/api/admin/subscription-payments/external', [
            'name' => 'Hamza Bilbao', 'monthly_fee' => 70,
        ])->json('data.id');
        foreach ([4, 5, 6] as $m) {
            $this->putJson("/api/admin/subscription-payments/external/{$externalId}/2026/{$m}", ['paid' => true, 'amount' => 70])->assertStatus(200);
        }
        // El usuario ya tenía junio pagado con otro importe: se respeta el suyo.
        SubscriptionPaymentRecord::create(['user_id' => $hamsa->id, 'year' => 2026, 'month' => 6, 'amount' => 65, 'paid' => true]);

        $candidates = $this->getJson('/api/admin/subscription-payments/merge-candidates')->assertStatus(200);
        $this->assertSame($hamsa->id, $candidates->json('data.0.candidates.0.id'));
        $this->assertCount(1, $candidates->json('data.0.candidates'));

        $this->postJson("/api/admin/subscription-payments/external/{$externalId}/merge", ['user_id' => $hamsa->id])
            ->assertStatus(200)
            ->assertJsonPath('data.moved', 2)
            ->assertJsonPath('data.skipped', 1);

        $this->assertNull(SubscriptionPaymentClient::find($externalId));
        $hamsa->refresh();
        $this->assertTrue((bool) $hamsa->is_personal_client);
        $this->assertEquals(70, $hamsa->monthly_fee);
        $this->assertEquals(65, SubscriptionPaymentRecord::where('user_id', $hamsa->id)->where('month', 6)->value('amount'));
        $this->assertSame(3, SubscriptionPaymentRecord::where('user_id', $hamsa->id)->count());

        $rows = collect($this->getJson('/api/admin/subscription-payments?year=2026')->json('data.clients'));
        $this->assertCount(1, $rows);
        $this->assertSame('user', $rows[0]['source']);
    }
}
