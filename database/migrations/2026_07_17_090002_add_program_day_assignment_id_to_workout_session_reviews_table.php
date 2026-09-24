<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // workout_day_id pasa a ser opcional (SQL directo, sin doctrine/dbal).
        // FASE 0 (docs/PLAN_CLONADO_PROGRAMAS.md, entorno de tests): "MODIFY"
        // es solo-MySQL -- en sqlite (tests locales) se usa ->change() nativo
        // de Laravel 11 para el mismo resultado, MySQL en producción no cambia.
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('workout_session_reviews', function (Blueprint $table) {
                $table->unsignedBigInteger('workout_day_id')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE workout_session_reviews MODIFY workout_day_id BIGINT UNSIGNED NULL');
        }

        Schema::table('workout_session_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('program_day_assignment_id')->nullable()->after('workout_day_id');
            $table->foreign('program_day_assignment_id')->references('id')->on('program_day_assignments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('workout_session_reviews', function (Blueprint $table) {
            $table->dropForeign(['program_day_assignment_id']);
            $table->dropColumn('program_day_assignment_id');
        });
    }
};
