<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ítem 49 del roadmap: asignar a mano el entrenador de un cliente desde su
 * ficha del panel (GET/PUT admin/users/{id}/coach).
 */
class AdminClientCoachAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');
    }

    private function makeUser(string $type, string $status = 'active', ?string $name = null): User
    {
        $u = User::create([
            'first_name' => $name ?? ucfirst($type), 'last_name' => 'Test', 'username' => $type.'_'.uniqid(),
            'email' => uniqid().'@example.test', 'password' => bcrypt('password'),
            'user_type' => $type, 'status' => $status, 'login_type' => 'manual',
        ]);
        // Igual que en el resto de tests del panel: coach y admin entran por el rol 'admin' (admin.api).
        $u->assignRole($type === 'user' ? 'user' : 'admin');

        return $u;
    }

    public function test_muestra_el_coach_actual_y_los_candidatos_activos(): void
    {
        $admin = $this->makeUser('admin', 'active', 'Ana');
        $coach = $this->makeUser('coach', 'active', 'Carlos');
        $this->makeUser('coach', 'inactive', 'Baja'); // no debe aparecer
        $client = $this->makeUser('user');
        $client->forceFill(['coach_id' => $coach->id])->save();
        Sanctum::actingAs($admin, ['*']);

        $res = $this->getJson("/api/admin/users/{$client->id}/coach")->assertOk();

        $this->assertSame($coach->id, $res->json('data.coach.id'));
        $names = collect($res->json('data.options'))->pluck('name')->all();
        $this->assertContains('Carlos Test', $names);
        $this->assertContains('Ana Test', $names);
        $this->assertNotContains('Baja Test', $names);
        // Los clientes no son candidatos.
        $this->assertCount(2, $res->json('data.options'));
    }

    public function test_un_admin_asigna_y_quita_el_entrenador_y_queda_auditado(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach', 'active', 'Carlos');
        $client = $this->makeUser('user');
        Sanctum::actingAs($admin, ['*']);

        $this->putJson("/api/admin/users/{$client->id}/coach", ['coach_id' => $coach->id])
            ->assertOk()
            ->assertJsonPath('data.coach.id', $coach->id);
        $this->assertSame($coach->id, $client->fresh()->coach_id);
        $this->assertTrue(AuditLog::where('entity_type', 'users')->where('entity_id', (string) $client->id)->where('detail', 'like', '%sin entrenador → Carlos Test%')->exists());

        $this->putJson("/api/admin/users/{$client->id}/coach", ['coach_id' => null])
            ->assertOk()
            ->assertJsonPath('data.coach', null);
        $this->assertNull($client->fresh()->coach_id);
    }

    public function test_un_admin_puede_ser_el_entrenador(): void
    {
        $admin = $this->makeUser('admin');
        $client = $this->makeUser('user');
        Sanctum::actingAs($admin, ['*']);

        $this->putJson("/api/admin/users/{$client->id}/coach", ['coach_id' => $admin->id])->assertOk();
        $this->assertSame($admin->id, $client->fresh()->coach_id);
    }

    public function test_rechaza_un_entrenador_inexistente_inactivo_o_que_es_un_cliente(): void
    {
        $admin = $this->makeUser('admin');
        $client = $this->makeUser('user');
        $other = $this->makeUser('user');
        $inactive = $this->makeUser('coach', 'inactive');
        Sanctum::actingAs($admin, ['*']);

        foreach ([999999, $inactive->id, $other->id] as $badId) {
            $this->putJson("/api/admin/users/{$client->id}/coach", ['coach_id' => $badId])->assertStatus(422);
        }
        $this->assertNull($client->fresh()->coach_id);
    }

    public function test_exige_el_campo_coach_id(): void
    {
        $admin = $this->makeUser('admin');
        $client = $this->makeUser('user');
        Sanctum::actingAs($admin, ['*']);

        $this->putJson("/api/admin/users/{$client->id}/coach", [])->assertStatus(422);
    }

    public function test_un_coach_no_puede_reasignar_clientes(): void
    {
        $coach = $this->makeUser('coach');
        $client = $this->makeUser('user');
        Sanctum::actingAs($coach, ['*']);

        $this->putJson("/api/admin/users/{$client->id}/coach", ['coach_id' => $coach->id])->assertStatus(403);
        $this->assertNull($client->fresh()->coach_id);
    }

    public function test_solo_funciona_con_clientes(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        Sanctum::actingAs($admin, ['*']);

        $this->getJson("/api/admin/users/{$coach->id}/coach")->assertStatus(404);
        $this->putJson('/api/admin/users/999999/coach', ['coach_id' => $admin->id])->assertStatus(404);
    }

    public function test_un_cliente_normal_no_accede(): void
    {
        $client = $this->makeUser('user');
        $other = $this->makeUser('user');
        Sanctum::actingAs($client, ['*']);

        $this->getJson("/api/admin/users/{$other->id}/coach")->assertStatus(403);
    }
}
