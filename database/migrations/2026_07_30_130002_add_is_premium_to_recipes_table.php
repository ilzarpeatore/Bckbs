<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flag de exclusividad para el gating Free vs Paquete (2026-07-30):
     * una receta marcada is_premium=true solo se ve completa si el usuario
     * es cliente 1:1 o tiene un Package activo con grants_full_recipe_library.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('is_premium')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('is_premium');
        });
    }
};
