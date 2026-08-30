<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrupación de recipe_tags (item 11 del backlog), p.ej. duration,
     * fat_loss, muscle_gain, performance, spain_regional, country, diet,
     * meal_type, other. String libre, no enum de BD, igual que `status` en
     * esta misma tabla.
     */
    public function up(): void
    {
        Schema::table('recipe_tags', function (Blueprint $table) {
            $table->string('group')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_tags', function (Blueprint $table) {
            $table->dropColumn('group');
        });
    }
};
