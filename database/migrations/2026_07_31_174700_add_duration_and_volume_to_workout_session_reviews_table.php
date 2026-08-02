<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cierre de sesion real desde la app cliente (boton "FINALIZAR
     * ENTRENAMIENTO" en workout_session_screen.tsx) - antes solo existia
     * el review de admin/coach (difficulty_rating/comment), sin duracion
     * ni volumen.
     */
    public function up(): void
    {
        Schema::table('workout_session_reviews', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('program_day_assignment_id');
            $table->decimal('volume_kg', 10, 2)->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('workout_session_reviews', function (Blueprint $table) {
            $table->dropColumn(['duration_seconds', 'volume_kg']);
        });
    }
};
