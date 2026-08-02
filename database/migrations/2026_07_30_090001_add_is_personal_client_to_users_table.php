<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cliente de entrenamiento personal 1:1 = acceso completo automático a todo
     * (Nutrición + Entrenamiento), sin necesidad de comprar ningún Package.
     * Toggle manual desde el admin, no derivado de si ya tiene contenido asignado.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_personal_client')->default(false)->after('coach_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_personal_client');
        });
    }
};
