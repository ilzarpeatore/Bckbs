<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Habilita 3 cosas para la app de cliente (antes solo existía el flujo
     * admin-asigna-directo): (1) coach_id nullable, para que un cliente
     * pueda crear su propio hábito personal sin coach; (2) source_template_id,
     * para saber que un hábito de cliente viene de la biblioteca global
     * (coach_id null + client_id null = plantilla, ver comentario en la
     * migración original de habits) en vez de ser 100% propio; (3) el índice
     * que necesita esa FK auto-referenciada.
     *
     * SQL directo para coach_id, no ->change(), mismo motivo documentado en
     * 2026_07_16_110001_make_workout_id_nullable_in_training_programs_table.php:
     * requiere doctrine/dbal, que no está en composer.lock.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE habits MODIFY coach_id BIGINT UNSIGNED NULL');

        Schema::table('habits', function (Blueprint $table) {
            $table->unsignedBigInteger('source_template_id')->nullable()->after('client_id');
            $table->foreign('source_template_id')->references('id')->on('habits')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('habits', function (Blueprint $table) {
            $table->dropForeign(['source_template_id']);
            $table->dropColumn('source_template_id');
        });

        DB::statement('ALTER TABLE habits MODIFY coach_id BIGINT UNSIGNED NOT NULL');
    }
};
