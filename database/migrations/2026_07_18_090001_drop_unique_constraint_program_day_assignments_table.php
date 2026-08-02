<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CORREGIDO: comprueba si el índice existe antes de borrarlo — si ya
     * se quitó a mano (como en este caso, por tinker), simplemente no
     * hace nada en vez de fallar.
     */
    public function up(): void
    {
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
