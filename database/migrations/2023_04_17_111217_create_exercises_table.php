<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExercisesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('instruction')->nullable();
            $table->text('tips')->nullable();
            $table->string('video_type')->nullable();
            $table->text('video_url')->nullable();
            $table->text('bodypart_ids')->nullable();
            $table->string('duration')->nullable();
            $table->string('based')->comment('reps, time')->nullable();
            $table->string('type')->comment('sets, duration')->nullable();
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->unsignedBigInteger('level_id')->nullable();
            $table->json('sets')->nullable();
            $table->string('status')->nullable()->default('active');
            $table->boolean('is_premium')->default(0)->comment('0-free, 1-premium')->nullable();
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
        Schema::dropIfExists('exercises');
    }
}
