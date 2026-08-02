<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si no existe fila para una feature+cliente, se asume habilitada
     * por defecto (opt-out, no opt-in) — así no hace falta poblar la
     * tabla para todos los clientes existentes al desplegar esto.
     */
    public function up(): void
    {
        Schema::create('client_feature_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('feature_key'); // workout|nutrition|habits|forms|resources|chatbot
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['client_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_feature_settings');
    }
};
