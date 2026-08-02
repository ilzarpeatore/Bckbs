<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Corrección de arquitectura: un training_program pasa a ser una
     * PLANTILLA reutilizable (como los workout_templates y
     * section_templates) — se crea una vez, sin cliente, y se asigna
     * después a cada cliente por separado vía `program_client_assignments`.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE training_programs MODIFY client_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE training_programs MODIFY client_id BIGINT UNSIGNED NOT NULL');
    }
};
