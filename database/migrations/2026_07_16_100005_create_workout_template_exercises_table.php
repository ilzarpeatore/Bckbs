<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_template_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workout_template_block_id');
            $table->unsignedBigInteger('exercise_id');
            $table->unsignedInteger('sequence')->default(0);
            $table->json('prescribed')->nullable();
            $table->json('enabled_metrics')->nullable();
            $table->timestamps();

            $table->foreign('workout_template_block_id')->references('id')->on('workout_template_blocks')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_template_exercises');
    }
};
