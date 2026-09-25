<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajuste de privacidad opt-in: el usuario decide si otros usuarios pueden ver
     * en su perfil (app, Comunidad) un resumen agregado de sus entrenamientos.
     * Apagado por defecto: nadie comparte nada sin haberlo activado él mismo.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_public_stats')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('show_public_stats');
        });
    }
};
