<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se rellena mediante un listener disparado al completar una sesión
     * (calcula 1RM con fórmula Epley y compara contra el récord anterior).
     * No se recalcula en tiempo real cada vez que se consulta el historial.
     */
    public function up(): void
    {
        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('exercise_id');
            $table->string('record_type'); // max_weight|max_1rm|max_volume
            $table->decimal('value', 8, 2);
            $table->timestamp('achieved_at');
            $table->unsignedBigInteger('workout_day_exercise_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_records');
    }
};
