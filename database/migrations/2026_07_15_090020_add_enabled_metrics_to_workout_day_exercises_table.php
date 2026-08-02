<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Al añadir un ejercicio a un día (desde el panel Admin), el coach elige
     * de un desplegable multi-select qué métricas de metrics_catalog aplican
     * a ESE ejercicio en concreto (ej. sentadilla: series/reps/carga/rpe;
     * plancha: series/tiempo). Se guarda como array de keys.
     */
    public function up(): void
    {
        Schema::table('workout_day_exercises', function (Blueprint $table) {
            $table->json('enabled_metrics')->nullable()->after('sets');
        });
    }

    public function down(): void
    {
        Schema::table('workout_day_exercises', function (Blueprint $table) {
            $table->dropColumn('enabled_metrics');
        });
    }
};
