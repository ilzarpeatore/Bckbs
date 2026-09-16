<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ver docs/AGENTE_IMPORTADOR.md (proyecto AgenticdesignBS) y el encargo
// BRIEF_registro_alergias_intolerancias.md: sin severidad estructurada, ningún
// agente ni humano revisando rápido puede saber si una alergia declarada por
// un cliente necesita exclusión con cuidado normal o con verificación de
// trazas/contaminación cruzada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_limitations', function (Blueprint $table) {
            $table->string('severity')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('client_limitations', function (Blueprint $table) {
            $table->dropColumn('severity');
        });
    }
};
