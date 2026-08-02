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
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->unsignedBigInteger('ingredient_id')->nullable();
            $table->unsignedBigInteger('measurement_unit_id')->nullable();
            $table->double('quantity')->default(0);
            $table->double('quantity_grams')->default(0)->comment('Calculated grams');
            $table->double('amount')->nullable()->comment('Gram equivalent of 1 unit');
            $table->double('calories')->default(0);
            $table->double('protein')->default(0);
            $table->double('fats')->default(0);
            $table->double('carbs')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
