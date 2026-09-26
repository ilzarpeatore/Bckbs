<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Caso Ayoub (2026-09-21..24): un cliente dice que rellenó las series y en el
 * servidor no hay ninguna. Un guardado rechazado no dejaba rastro, así que no
 * se podía distinguir "nunca lo envió" de "el servidor lo rechazó". Ahora
 * logSets() registra un warning en cada rechazo.
 */
class LogSetsRejectionLoggingTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private int $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');

        $this->client = User::create([
            'first_name' => 'Test', 'last_name' => 'User', 'username' => 'u_'.uniqid(),
            'email' => uniqid().'@example.test', 'password' => bcrypt('p'),
            'user_type' => 'user', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $this->client->assignRole('user');
        $this->exercise = DB::table('exercises')->insertGetId(['title' => 'Press banca', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($this->client);
    }

    public function test_un_rechazo_por_validacion_deja_un_warning_con_el_cliente_y_los_errores(): void
    {
        Log::spy();

        $this->postJson('/api/v1/my-calendar-log-sets', [
            'exercise_id' => 999999, // no existe
            'logged_sets' => [['reps' => 10, 'carga' => 60, 'rir' => 2]],
            'session_key' => 'pda:1:123',
        ])->assertStatus(422);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return str_contains($message, 'rechazado por validación')
                && $context['client_id'] === $this->client->id
                && $context['session_key'] === 'pda:1:123'
                && $context['sets_count'] === 1
                && isset($context['errors']['exercise_id']);
        })->once();
    }

    public function test_una_serie_sin_rir_ni_rpe_se_rechaza_y_deja_un_warning(): void
    {
        Log::spy();

        $this->postJson('/api/v1/my-calendar-log-sets', [
            'exercise_id' => $this->exercise,
            'logged_sets' => [['reps' => 10, 'carga' => 60]], // sin RIR/RPE
        ])->assertStatus(422);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return str_contains($message, 'falta RIR/RPE')
                && $context['client_id'] === $this->client->id
                && $context['exercise_id'] === $this->exercise
                && $context['set_index'] === 0;
        })->once();
    }

    public function test_un_guardado_correcto_no_deja_warnings(): void
    {
        Log::spy();

        $this->postJson('/api/v1/my-calendar-log-sets', [
            'exercise_id' => $this->exercise,
            'logged_sets' => [['reps' => 10, 'carga' => 60, 'rir' => 2]],
        ])->assertOk();

        Log::shouldNotHaveReceived('warning');
    }
}
