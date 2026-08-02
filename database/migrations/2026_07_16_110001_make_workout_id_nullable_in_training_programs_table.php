<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Se usa SQL directo en vez de ->change() de Laravel, porque ese
     * método requiere el paquete doctrine/dbal instalado, y no lo vimos
     * en tu composer.lock — así evitamos añadir una dependencia nueva
     * solo para este cambio puntual.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE training_programs MODIFY workout_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE training_programs MODIFY workout_id BIGINT UNSIGNED NOT NULL');
    }
};
