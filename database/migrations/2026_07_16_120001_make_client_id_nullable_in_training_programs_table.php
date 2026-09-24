<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corrección de arquitectura: un training_program pasa a ser una
     * PLANTILLA reutilizable (como los workout_templates y
     * section_templates) — se crea una vez, sin cliente, y se asigna
     * después a cada cliente por separado vía `program_client_assignments`.
     *
     * FASE 0 (docs/PLAN_CLONADO_PROGRAMAS.md, entorno de tests): mismo
     * ajuste que 2026_07_16_110001_make_workout_id_nullable... -- "ALTER
     * TABLE ... MODIFY" es solo-MySQL, sqlite (tests locales) usa el
     * ->change() nativo de Laravel 11 para el mismo resultado. MySQL en
     * producción no cambia de camino.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
            });
            return;
        }

        DB::statement('ALTER TABLE training_programs MODIFY client_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable(false)->change();
            });
            return;
        }

        DB::statement('ALTER TABLE training_programs MODIFY client_id BIGINT UNSIGNED NOT NULL');
    }
};
