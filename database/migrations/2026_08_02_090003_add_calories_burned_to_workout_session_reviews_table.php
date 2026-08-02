<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antes la app mostraba "0" fijo en Calorias durante la sesion - no
     * existia ningun calculo real. Se calcula server-side en finishSession
     * (MET resistencia * peso del perfil * horas) y se persiste aqui.
     */
    public function up(): void
    {
        Schema::table('workout_session_reviews', function (Blueprint $table) {
            $table->decimal('calories_burned', 8, 2)->nullable()->after('volume_kg');
        });
    }

    public function down(): void
    {
        Schema::table('workout_session_reviews', function (Blueprint $table) {
            $table->dropColumn('calories_burned');
        });
    }
};
