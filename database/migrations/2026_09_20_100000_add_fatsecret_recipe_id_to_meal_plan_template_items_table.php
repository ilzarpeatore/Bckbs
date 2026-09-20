<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FIX (2026-09-20, reportado por el usuario): sin esto, guardar como
     * plantilla un rango de calendario que incluyera una comida de FatSecret
     * fallaba con un error real de base de datos -- recipe_id era NOT NULL
     * en esta tabla, y una comida de FatSecret tiene recipe_id null. Mismo
     * patron que daily_plan_recipes.fatsecret_recipe_id (ver
     * docs/FATSECRET_INTEGRATION.md), exactamente una de las dos columnas
     * debe estar rellena.
     *
     * DB::statement en vez de ->change() -- este proyecto no tiene
     * doctrine/dbal instalado (requisito de ->change()), mismo patron ya
     * usado en 2026_07_16_110001_make_workout_id_nullable_in_training_programs_table.php.
     */
    public function up(): void
    {
        Schema::table('meal_plan_template_items', function (Blueprint $table) {
            $table->unsignedBigInteger('fatsecret_recipe_id')->nullable()->after('recipe_id');
        });

        DB::statement('ALTER TABLE meal_plan_template_items MODIFY recipe_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE meal_plan_template_items MODIFY recipe_id BIGINT UNSIGNED NOT NULL');

        Schema::table('meal_plan_template_items', function (Blueprint $table) {
            $table->dropColumn('fatsecret_recipe_id');
        });
    }
};
