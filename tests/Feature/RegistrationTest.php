<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        // RegisteredUserController::store() pide first_name/last_name (no
        // el 'name' del scaffolding de Breeze) y asigna el rol 'user'.
        Role::findOrCreate('user', 'web');
        // El evento Registered manda el mail de verificación; sin
        // MAIL_FROM_ADDRESS en el entorno de test el transporte 'array'
        // revienta -- aquí solo se prueba el alta, no el envío.
        Notification::fake();

        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }
}
