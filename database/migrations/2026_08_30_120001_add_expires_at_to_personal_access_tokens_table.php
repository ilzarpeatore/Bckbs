<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La migración original (2019_12_14_000001) es un stub de Sanctum
     * anterior a que el paquete añadiera expiración de tokens. Este repo
     * fija laravel/sanctum ^4 (composer.lock: v4.3.1) en
     * vendor/laravel/sanctum/src/HasApiTokens.php::createToken(), que ya
     * escribe en `expires_at` -- sin esta columna, CUALQUIER createToken()
     * (login, register, admin login) rompe con "no column named expires_at"
     * en una base de datos migrada desde cero.
     *
     * No se edita la migración de 2019 porque ya se ejecutó en producción
     * -- Laravel no la re-corre, así que ese cambio no tendría efecto ahí.
     * Esta migración nueva sí corre en cualquier entorno (producción
     * incluida) y añade la columna de forma idempotente si todavía falta.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('personal_access_tokens', 'expires_at')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->index()->after('last_used_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('personal_access_tokens', 'expires_at')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->dropIndex(['expires_at']);
                $table->dropColumn('expires_at');
            });
        }
    }
};
