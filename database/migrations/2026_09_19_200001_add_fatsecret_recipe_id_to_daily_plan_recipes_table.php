<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite que una entrada del calendario de comidas apunte a una receta
     * de FatSecret en vez de a un Recipe local -- exactamente una de las dos
     * columnas (recipe_id / fatsecret_recipe_id) debe estar rellena, nunca
     * las dos. No se guarda ningún otro dato de FatSecret aquí (nombre,
     * imagen, pasos) -- eso se pide en vivo a FatSecretRecipeCache cuando
     * hace falta mostrarlo, ver docs/FATSECRET_INTEGRATION.md sección 9.
     */
    public function up(): void
    {
        Schema::table('daily_plan_recipes', function (Blueprint $table) {
            $table->unsignedBigInteger('fatsecret_recipe_id')->nullable()->after('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('daily_plan_recipes', function (Blueprint $table) {
            $table->dropColumn('fatsecret_recipe_id');
        });
    }
};
