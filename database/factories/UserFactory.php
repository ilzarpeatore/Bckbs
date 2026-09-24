<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * AÑADIDO (2026-09-24): los tests de autenticación del panel web (Breeze:
 * AuthenticationTest, EmailVerificationTest, PasswordConfirmationTest,
 * PasswordResetTest) usan User::factory() y la carpeta database/factories
 * no existía -- fallaban con "Class Database\Factories\UserFactory not
 * found" sin llegar a probar nada. Columnas reales de `users` (username
 * y email son UNIQUE, status 'active' para pasar el middleware useractive)
 * y contraseña "password" como espera el scaffolding de Breeze.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * fakerphp/faker no está en composer.json (ni require ni require-dev):
     * el Factory base lo resuelve en su constructor y rompía con "Class
     * Faker\Factory not found". Esta factory no lo necesita -- datos
     * aleatorios con Str::random, sin añadir una dependencia nueva.
     */
    protected function withFaker()
    {
        return null;
    }

    public function definition(): array
    {
        return [
            'username'          => 'user_'.Str::lower(Str::random(12)),
            'first_name'        => 'Test',
            'last_name'         => 'User',
            'email'             => Str::lower(Str::random(16)).'@example.test',
            'email_verified_at' => now(),
            'password'          => bcrypt('password'),
            'user_type'         => 'user',
            'status'            => 'active',
            'login_type'        => 'manual',
            'remember_token'    => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
