<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traducción de recetas (2026-09-21, permiso explícito de FatSecret
     * obtenido el 2026-09-20 -- ver docs/FATSECRET_INTEGRATION.md sección
     * 10). `name`/`directions`/`ingredients` pasan a contener el texto YA
     * TRADUCIDO (o el original en inglés si la traducción falla/no está
     * disponible) -- así ningún código que ya lee esos campos
     * (NormalizesFatSecretRecipePreview, FatSecretController::show(), la
     * app, el panel) necesita ningún cambio. El inglés original se
     * conserva aparte en `*_en` para depuración/reprocesado si se cambia
     * de proveedor de traducción más adelante.
     */
    public function up(): void
    {
        Schema::table('fatsecret_recipe_cache', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->json('directions_en')->nullable()->after('directions');
            $table->json('ingredients_en')->nullable()->after('ingredients');
            $table->boolean('is_translated')->default(false)->after('ingredients_en');
        });
    }

    public function down(): void
    {
        Schema::table('fatsecret_recipe_cache', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'directions_en', 'ingredients_en', 'is_translated']);
        });
    }
};
