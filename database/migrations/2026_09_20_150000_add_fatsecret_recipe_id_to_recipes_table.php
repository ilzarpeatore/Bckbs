<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decisión del usuario 2026-09-20: guardar copia permanente del
     * contenido de recetas de FatSecret en la biblioteca propia (recipes),
     * pese a la reserva legal documentada en docs/FATSECRET_INTEGRATION.md
     * sección 0 (su ToS solo permite guardar IDs indefinidamente, el resto
     * de contenido habría que refrescarlo cada 24h) -- riesgo asumido
     * explícitamente, ver sección 11 de ese documento.
     *
     * Esta columna es la clave de idempotencia: antes de volver a importar
     * un fatsecret_recipe_id, se comprueba si ya existe una Recipe con este
     * valor para no duplicar cada vez que se usa en un plan nuevo.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->unsignedBigInteger('fatsecret_recipe_id')->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('fatsecret_recipe_id');
        });
    }
};
