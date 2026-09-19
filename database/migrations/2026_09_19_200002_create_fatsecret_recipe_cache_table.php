<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache-aside de contenido de receta de FatSecret -- NUNCA se trata como
     * dato propio permanente (ver docs/FATSECRET_INTEGRATION.md sección 0):
     * solo sirve para no repetir la llamada a la API cada vez que alguien
     * pide la misma receta en una ventana corta de tiempo. `fetched_at` se
     * usa para decidir si hay que volver a pedirla a FatSecret -- el TTL
     * (ver FatSecretRecipeCache::TTL_HOURS) se queda deliberadamente muy por
     * debajo de las 24h que permite su política, nunca hay que acercarse al
     * límite real.
     */
    public function up(): void
    {
        Schema::create('fatsecret_recipe_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('fatsecret_recipe_id')->primary();
            $table->string('name');
            $table->string('image_url')->nullable();
            $table->double('calories')->default(0);
            $table->double('protein')->default(0);
            $table->double('fat')->default(0);
            $table->double('carbs')->default(0);
            $table->decimal('number_of_servings', 8, 2)->nullable();
            $table->unsignedInteger('preparation_time_min')->nullable();
            $table->unsignedInteger('cooking_time_min')->nullable();
            $table->json('directions')->nullable();
            $table->json('ingredients')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatsecret_recipe_cache');
    }
};
