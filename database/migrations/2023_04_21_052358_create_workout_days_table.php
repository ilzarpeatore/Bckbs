<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkoutDaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workout_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workout_id')->nullable(); 
            $table->bigInteger('sequence')->nullable();
            $table->boolean('is_rest')->default(0)->comment('0-no,1-yes')->nullable();
            $table->foreign('workout_id')->references('id')->on('workouts')->onDelete('cascade');
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
        Schema::dropIfExists('workout_days');
    }
}
