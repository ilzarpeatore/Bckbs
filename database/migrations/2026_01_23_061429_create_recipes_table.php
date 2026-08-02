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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->unsignedInteger('preparation_time')->nullable()->comment('in minutes');
            $table->string('type')->comment('veg, non-veg, vegan')->default('veg');
            $table->text('meal_type')->comment('breakfast, lunch, snacks, dinner')->nullable();
            $table->text('description')->nullable();
            $table->double('calories')->default(0);
            $table->double('protein')->default(0);
            $table->double('fats')->default(0);
            $table->double('carbs')->default(0);
            $table->string('status')->nullable()->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
