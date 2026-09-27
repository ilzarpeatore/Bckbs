<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nuevo endpoint admin/users/lookup-by-phone: reemplaza gradualmente la hoja
 * de mapeo manual teléfono->cliente_id que hoy usa el Agente de Soporte /
 * Customer Success (docs/TAREAS_PENDIENTES.md de AgenticdesignBS, ítem
 * 2.16/2.20). Cubre el caso feliz (número guardado tal cual llega),
 * el caso real de datos sucios (número guardado sin el prefijo de país,
 * mientras WhatsApp siempre lo manda completo), ambigüedad (nunca elegir
 * por el usuario) y ausencia de match (cae al proceso manual).
 */
class AdminUserPhoneLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');
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

    private function makeClient(int $coachId, ?string $phoneNumber = null): User
    {
        $client = User::create([
            'first_name'   => 'Client',
            'last_name'    => 'Test',
            'username'     => 'client_' . uniqid(),
            'email'        => uniqid() . '@example.test',
            'password'     => bcrypt('password'),
            'user_type'    => 'user',
            'status'       => 'active',
            'login_type'   => 'manual',
            'phone_number' => $phoneNumber,
        ]);
        $client->assignRole('user');
        $client->forceFill(['coach_id' => $coachId])->save();

        return $client;
    }

    public function test_finds_client_by_exact_stored_phone_number(): void
    {
        $coach = $this->makeCoach();
        $client = $this->makeClient($coach->id, '34612345678');

        Sanctum::actingAs($coach, ['*']);
        $response = $this->getJson('/api/admin/users/lookup-by-phone?phone=' . urlencode('+34 612 345 678'));

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $client->id);
    }

    public function test_finds_client_when_stored_number_lacks_country_code(): void
    {
        $coach = $this->makeCoach();
        // Coach histórico que guardó solo el número nacional, sin prefijo de país.
        $client = $this->makeClient($coach->id, '612345678');

        Sanctum::actingAs($coach, ['*']);
        // WhatsApp/Twilio siempre manda el E.164 completo.
        $response = $this->getJson('/api/admin/users/lookup-by-phone?phone=' . urlencode('whatsapp:+34612345678'));

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $client->id);
    }

    public function test_returns_404_when_no_client_matches(): void
    {
        $coach = $this->makeCoach();
        Sanctum::actingAs($coach, ['*']);

        $response = $this->getJson('/api/admin/users/lookup-by-phone?phone=' . urlencode('+34600000000'));

        $response->assertStatus(404);
    }

    public function test_returns_409_when_ambiguous(): void
    {
        $coach = $this->makeCoach();
        // Dos clientes con el mismo número guardado -- posible hoy porque no
        // hay índice único a nivel de BD sobre `phone_number` (solo
        // validación de aplicación, que no cubre filas ya existentes antes
        // de este fix). El endpoint nunca debe elegir por el humano.
        $this->makeClient($coach->id, '34612345678');
        $this->makeClient($coach->id, '34612345678');

        Sanctum::actingAs($coach, ['*']);
        $response = $this->getJson('/api/admin/users/lookup-by-phone?phone=' . urlencode('+34612345678'));

        $response->assertStatus(409);
    }

    public function test_never_matches_a_coach_or_admin_phone_number(): void
    {
        $coach = $this->makeCoach();
        $coach->forceFill(['phone_number' => '34699999999'])->save();

        Sanctum::actingAs($coach, ['*']);
        $response = $this->getJson('/api/admin/users/lookup-by-phone?phone=' . urlencode('+34699999999'));

        $response->assertStatus(404);
    }
}
