<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkoutDayExercisesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workout_day_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workout_id')->nullable();
            $table->unsignedBigInteger('workout_day_id')->nullable();
            $table->unsignedBigInteger('exercise_id')->nullable();
            $table->json('sets')->nullable();
            $table->unsignedBigInteger('sequence')->nullable();
            $table->string('duration')->nullable();
            $table->foreign('workout_id')->references('id')->on('workouts')->onDelete('cascade');
            $table->foreign('workout_day_id')->references('id')->on('workout_days')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workout_day_exercises');
    }
}
