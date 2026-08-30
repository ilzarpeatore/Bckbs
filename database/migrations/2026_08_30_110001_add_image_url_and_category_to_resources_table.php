<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `image_url`: portada opcional de un recurso, seteada directamente
     * como string o subida como archivo desde el admin (ver
     * Admin\ResourceController::store/update).
     *
     * `category`: agrupación para recursos compartidos (guías), p.ej.
     * entrenamiento|nutricion|habitos_mindset. String libre, no enum de BD,
     * igual que `type`/`scope` en esta misma tabla.
     */
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('external_url');
            $table->string('category')->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'category']);
        });
    }
};
