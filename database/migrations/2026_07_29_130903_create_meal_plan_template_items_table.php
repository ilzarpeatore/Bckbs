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
        Schema::create('meal_plan_template_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meal_plan_template_id');
            $table->string('day_key')
                ->comment('sequential type: "0","1","2"... (day offset); weekday type: "monday".."sunday"');
            $table->string('meal_type')->comment('breakfast, lunch, dinner, snacks');
            $table->unsignedBigInteger('recipe_id');
            $table->double('calories')->nullable()->default(0);
            $table->double('protein')->nullable()->default(0);
            $table->double('fats')->nullable()->default(0);
            $table->double('carbs')->nullable()->default(0);
            $table->foreign('meal_plan_template_id')->references('id')->on('meal_plan_templates')->onDelete('cascade');
            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plan_template_items');
    }
};
