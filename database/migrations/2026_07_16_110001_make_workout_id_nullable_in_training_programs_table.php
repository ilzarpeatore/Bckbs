<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se usa SQL directo en vez de ->change() de Laravel, porque ese
     * método requiere el paquete doctrine/dbal instalado, y no lo vimos
     * en tu composer.lock — así evitamos añadir una dependencia nueva
     * solo para este cambio puntual.
     *
     * FASE 0 (docs/PLAN_CLONADO_PROGRAMAS.md, entorno de tests): "ALTER
     * TABLE ... MODIFY" es sintaxis exclusiva de MySQL -- sqlite (usado
     * solo en tests locales, nunca en producción) no la soporta y falla
     * con "near MODIFY: syntax error". En cualquier driver que no sea
     * MySQL se usa el ->change() nativo de Laravel 11 (ya no requiere
     * doctrine/dbal, a diferencia de cuando se escribió el comentario de
     * arriba) para llegar exactamente al mismo esquema resultante --
     * MySQL en producción sigue el mismo camino de SQL crudo de siempre,
     * sin cambio de comportamiento.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->unsignedBigInteger('workout_id')->nullable()->change();
            });
            return;
        }

        DB::statement('ALTER TABLE training_programs MODIFY workout_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->unsignedBigInteger('workout_id')->nullable(false)->change();
            });
            return;
        }

        DB::statement('ALTER TABLE training_programs MODIFY workout_id BIGINT UNSIGNED NOT NULL');
    }
};
