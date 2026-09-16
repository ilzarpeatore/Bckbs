<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplia la tabla `tasks` ya existente (2026_07_21) para el Panel de
     * Tareas pedido por el usuario -- 2 tipos en la misma tabla en vez de
     * una tabla nueva, para poder listarlos/filtrarlos juntos sin duplicar
     * el CRUD ya construido en TaskController:
     *
     * - `management` (default, compatible con las filas ya existentes):
     *   las que el admin apunta a mano. Gana `category`.
     * - `dev`: sincronizadas por Claude Code desde docs/ROADMAP.md de
     *   `bsa` via el endpoint `task-sync`. Usa source_key/source_repo/
     *   source_url en vez de client_id/priority/due_date/category.
     *   `source_key` es el identificador estable del item en el roadmap
     *   (ej. "0a", "0a-bis") para poder hacer upsert sin duplicar.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('type')->default('management')->after('id'); // management|dev
            $table->string('category')->nullable()->after('priority'); // entrenamiento|nutricion|revisiones|otro (solo management)
            $table->string('source_key')->nullable()->after('category');
            $table->string('source_repo')->nullable()->after('source_key'); // bsa|Bckbs|bstronger-admin (solo dev)
            $table->string('source_url')->nullable()->after('source_repo');
            $table->timestamp('completed_at')->nullable()->after('status');

            $table->index('type');
            $table->index(['type', 'source_repo', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['type', 'source_repo', 'source_key']);
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'category', 'source_key', 'source_repo', 'source_url', 'completed_at']);
        });
    }
};
