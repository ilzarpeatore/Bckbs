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
        Schema::create('diet_meal_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('diet_id');
            $table->string('meal_type')->comment('breakfast, lunch, dinner, snacks');
            $table->unsignedBigInteger('recipe_id');
            $table->double('calories')->nullable()->default(0);
            $table->double('protein')->nullable()->default(0);
            $table->double('fats')->nullable()->default(0);
            $table->double('carbs')->nullable()->default(0);
            $table->foreign('diet_id')->references('id')->on('diets')->onDelete('cascade');
            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diet_meal_items');
    }
};
