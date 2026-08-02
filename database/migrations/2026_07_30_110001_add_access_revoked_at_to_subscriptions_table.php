<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda de idempotencia para PackageFulfillmentService::revokeAccess():
     * distinta de fulfilled_at (que marca "se importó el contenido"). Evita
     * que el comando programado check:subscription vuelva a intentar borrar
     * entradas de calendario ya retiradas en una ejecución anterior.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('access_revoked_at')->nullable()->after('fulfilled_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('access_revoked_at');
        });
    }
};
