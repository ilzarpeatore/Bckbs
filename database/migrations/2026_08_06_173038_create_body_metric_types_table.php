<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('body_metric_types', function (Blueprint $table) {
            $table->id();
            // Clave de negocio usada como metric_type en client_body_metrics.
            // No es UNIQUE a nivel de BD a propósito: dos clientes distintos
            // pueden tener cada uno un tipo 'client' con el mismo value sin
            // chocar (MySQL trata NULL != NULL en indices unique, asi que un
            // unique(value, client_id) dejaria colisionar los global de
            // todas formas) — la unicidad por scope se valida en el controller.
            $table->string('value', 60);
            $table->string('label', 100);
            $table->string('unit', 20)->nullable();
            $table->enum('scope', ['global', 'client'])->default('global');
            $table->foreignId('client_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['scope', 'client_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('body_metric_types');
    }
};
