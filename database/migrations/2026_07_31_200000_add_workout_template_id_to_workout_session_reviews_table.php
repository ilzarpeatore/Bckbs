<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite cerrar sesion (con feedback) de un Workout suelto iniciado
     * por workout_template_id, no solo de un dia de programa asignado -
     * antes finishSession exigia siempre program_day_assignment_id.
     */
    public function up(): void
    {
        Schema::table('workout_session_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('workout_template_id')->nullable()->after('program_day_assignment_id');
            $table->foreign('workout_template_id')->references('id')->on('workout_templates')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('workout_session_reviews', function (Blueprint $table) {
            $table->dropForeign(['workout_template_id']);
            $table->dropColumn('workout_template_id');
        });
    }
};
