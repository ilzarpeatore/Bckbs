<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solo tiene sentido en plantillas (client_id null) — agrupa la biblioteca
     * global visualmente (ej. "Más Populares", "Saludable"), igual que hacía
     * la biblioteca hardcodeada original del admin panel, pero ahora como
     * dato real en vez de localStorage.
     */
    public function up(): void
    {
        Schema::table('habits', function (Blueprint $table) {
            $table->string('category')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('habits', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
