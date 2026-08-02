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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('ingredient_category_id')->nullable();
            $table->double('density')->default(1.0)->comment('g/ml');
            $table->double('calories_per_gram')->default(0);
            $table->double('protein_per_gram')->default(0);
            $table->double('fat_per_gram')->default(0);
            $table->double('carbs_per_gram')->default(0);
            $table->string('status')->nullable()->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
