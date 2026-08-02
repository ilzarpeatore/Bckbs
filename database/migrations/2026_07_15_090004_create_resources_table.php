<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recursos compartidos (client_id = null, visibles para todo el roster)
     * o personales (client_id = X, solo para ese cliente).
     * `content` guarda Markdown o JSON de bloques, según se decida en el
     * renderer de Flutter (ver sección "Formato de los recursos" del análisis).
     */
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('title');
            $table->string('type')->default('article'); // article|video|link|doc
            $table->longText('content')->nullable();
            $table->string('external_url')->nullable();
            $table->string('scope')->default('shared'); // shared|personal
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
