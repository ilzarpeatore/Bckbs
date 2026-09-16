<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Panel de Tareas del admin (pedido explícito, ver bsa docs/ROADMAP.md):
     * unifica 2 tipos en una sola tabla para poder listarlos/filtrarlos
     * juntos en la UI -- `type` decide qué campos aplican.
     *
     * - `management`: tareas que el admin apunta a mano (diseñar
     *   entrenamientos, nutrición, revisiones de clientes...). Usa
     *   category/priority/due_date/client_id/created_by.
     * - `dev`: sincronizadas por Claude Code desde los .md de pendientes de
     *   `bsa` (docs/ROADMAP.md) vía el endpoint `admin-tasks-sync` -- usa
     *   source_key/source_repo/source_url en vez de category/priority/etc.
     *   `source_key` es el identificador estable del item en el roadmap
     *   (ej. "0a", "0a-bis") para poder hacer upsert sin duplicar.
     */
    public function up(): void
    {
        Schema::create('admin_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // management|dev
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending|in_progress|done

            // Solo `management`
            $table->string('category')->nullable(); // entrenamiento|nutricion|revisiones|otro
            $table->string('priority')->nullable(); // alta|media|baja
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            // Solo `dev`
            $table->string('source_key')->nullable();
            $table->string('source_repo')->nullable(); // bsa|Bckbs|bstronger-admin
            $table->string('source_url')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index('type');
            $table->index('status');
            $table->index(['type', 'source_repo', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_tasks');
    }
};
