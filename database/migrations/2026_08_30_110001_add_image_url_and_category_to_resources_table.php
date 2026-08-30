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
        // `category` ya existía en algunos entornos (añadida ad-hoc antes de
        // que esta migración se formalizara) -- idempotente para no romper
        // en entornos donde una, ninguna o ambas columnas ya están.
        Schema::table('resources', function (Blueprint $table) {
            if (!Schema::hasColumn('resources', 'image_url')) {
                $table->string('image_url')->nullable()->after('external_url');
            }
            if (!Schema::hasColumn('resources', 'category')) {
                $table->string('category')->nullable()->after('scope');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $columns = array_filter(['image_url', 'category'], fn ($c) => Schema::hasColumn('resources', $c));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
