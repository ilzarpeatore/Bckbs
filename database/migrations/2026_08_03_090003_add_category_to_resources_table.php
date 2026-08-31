<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-seccion dentro de cada pestaña de la app:
 * - scope=shared   -> entrenamiento | nutricion | habitos_mindset
 * - scope=assigned -> onboarding | planes_actuales
 * Se guarda como string libre (sin ENUM en BD, igual que `scope`/`type`);
 * la validacion de los valores permitidos vive en el controlador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('category')->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
