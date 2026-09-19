<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->unsignedBigInteger('fatsecret_food_id')->nullable()->unique()->after('carbs_per_gram');
            // Qué ración (serving) de FatSecret se usó para calcular el
            // *_per_gram -- un food_id puede tener varias raciones, y el
            // refresco periódico tiene que recalcular con la MISMA ración,
            // no con la que FatSecret marque como "default" ese día (ver
            // docs/FATSECRET_INTEGRATION.md sección 4.2).
            $table->unsignedBigInteger('fatsecret_serving_id')->nullable()->after('fatsecret_food_id');
            $table->timestamp('fatsecret_synced_at')->nullable()->after('fatsecret_serving_id');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['fatsecret_food_id', 'fatsecret_serving_id', 'fatsecret_synced_at']);
        });
    }
};
