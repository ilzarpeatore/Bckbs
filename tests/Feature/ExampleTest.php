<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz del panel web (HomeController::index) va detrás de
     * middleware auth: un invitado debe acabar en el login, no ver el
     * dashboard (el 200 del scaffolding original no aplica a esta app).
     */
    public function testGuestIsRedirectedToLogin()
    {
        $this->get('/')->assertRedirect('/login');
    }
}
