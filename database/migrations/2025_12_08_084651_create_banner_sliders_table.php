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
        Schema::create('banner_sliders', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->enum('type', ['workout', 'url'])->default('workout');
            $table->unsignedBigInteger('workout_id')->nullable();
            $table->string('url')->nullable();
            $table->string('status')->nullable()->default('active');
            $table->foreign('workout_id')->references('id')->on('workouts')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banner_sliders');
    }
};
