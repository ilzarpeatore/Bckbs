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
        Schema::create('daily_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('date')->nullable();
            $table->double('daily_kcal')->nullable()->default(0);
            $table->double('eaten')->nullable()->default(0);
            $table->double('left_eat')->nullable()->default(0);
            $table->double('calories')->nullable()->default(0);
            $table->double('protein')->nullable()->default(0);
            $table->double('fats')->nullable()->default(0);
            $table->double('carbs')->nullable()->default(0);
            $table->text('daily_plan')->nullable();
            $table->text('meal_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_plans');
    }
};
