<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Con el programa ahora como plantilla reutilizable (sin cliente
     * fijo), necesita un nombre propio para identificarlo — antes se
     * identificaba implícitamente por su cliente/workout asociado.
     */
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->string('title')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
