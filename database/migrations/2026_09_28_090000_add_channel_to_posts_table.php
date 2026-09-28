<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El blog de la app y el de la web (ambos consumen GET post-list, misma
     * API) estaban sincronizados sin ningún filtro -- el usuario quiere que
     * el contenido orientado a captación (agente Copywriter Comercial) salga
     * solo en la web, nunca en la app, mientras que el contenido educativo
     * (agente Copywriter) siga apareciendo en las dos, igual que hoy.
     *
     * default 'both': ningún post existente ni ningún consumidor que todavía
     * no mande el parámetro `channel` en GET post-list ve cambiar nada --
     * el filtro solo entra en juego cuando la app/web empiecen a pedir un
     * canal concreto (ver el diseño de los agentes de contenido en
     * AgenticdesignBS y el aviso pendiente para los repos de app/web, fuera
     * de este backend).
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('channel', 10)->default('both')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
