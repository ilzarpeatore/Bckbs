<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CORREGIDO: se quita `->after('workout_day_id')` porque esa columna
     * no existe en la tabla `user_exercises` real de este proyecto — la
     * columna `logged_sets` se añade igual, solo que al final de la
     * tabla en vez de en una posición concreta (esto no afecta en nada
     * a su funcionamiento, solo al orden visual de columnas en un
     * cliente de BD).
     */
    public function up(): void
    {
        Schema::table('user_exercises', function (Blueprint $table) {
            $table->json('logged_sets')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_exercises', function (Blueprint $table) {
            $table->dropColumn('logged_sets');
        });
    }
};
