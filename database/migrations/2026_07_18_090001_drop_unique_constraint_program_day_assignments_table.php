<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CORREGIDO: comprueba si el índice existe antes de borrarlo — si ya
     * se quitó a mano (como en este caso, por tinker), simplemente no
     * hace nada en vez de fallar.
     *
     * FASE 0 (docs/PLAN_CLONADO_PROGRAMAS.md, entorno de tests): "SHOW
     * INDEX" / "DROP FOREIGN KEY" / "ADD CONSTRAINT" son sintaxis
     * exclusiva de MySQL, sqlite (tests locales) no las soporta. El
     * objetivo real de esta migración es solo quitar el índice único
     * 'pda_program_week_day_unique' (creado en
     * 2026_07_16_100006_create_program_day_assignments_table.php, que
     * SIEMPRE existe en una base de datos de test recién migrada) -- en
     * cualquier driver que no sea MySQL basta con el dropUnique() nativo
     * de Laravel, que sqlite soporta de forma directa (DROP INDEX) sin
     * tener que tocar la FK por separado. MySQL en producción sigue
     * exactamente el mismo camino de siempre, sin cambio de comportamiento.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('program_day_assignments', function (Blueprint $table) {
                $table->dropUnique('pda_program_week_day_unique');
            });
            return;
        }

        $exists = DB::select("SHOW INDEX FROM program_day_assignments WHERE Key_name = 'pda_program_week_day_unique'");

        if (empty($exists)) {
            return; // ya no existe, nada que hacer
        }

        DB::statement('ALTER TABLE program_day_assignments DROP FOREIGN KEY program_day_assignments_training_program_id_foreign');
        DB::statement('ALTER TABLE program_day_assignments DROP INDEX pda_program_week_day_unique');
        DB::statement('ALTER TABLE program_day_assignments ADD CONSTRAINT program_day_assignments_training_program_id_foreign FOREIGN KEY (training_program_id) REFERENCES training_programs(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        // No se revierte: recrear la restricción única podría fallar si ya
        // existen días con más de un entrenamiento asignado.
    }
};
