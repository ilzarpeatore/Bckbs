<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Herramienta temporal de desarrollo: FAB en cada pantalla de la app
     * para que el usuario marque "borrar"/"terminada"/"no entiendo" +
     * nota libre, y Claude Code pueda leer esas notas despues via tinker/
     * el endpoint de listado. Se borrara esta tabla junto con el resto del
     * feature cuando ya no haga falta (ver ScreenReviewFab.tsx en la app).
     */
    public function up(): void
    {
        Schema::create('screen_review_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('route_name');
            $table->enum('status', ['delete', 'done', 'confused']);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screen_review_marks');
    }
};
