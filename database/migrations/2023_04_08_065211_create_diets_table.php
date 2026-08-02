<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDietsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('diets', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('categorydiet_id')->nullable();
            $table->string('calories')->nullable();
            $table->string('carbs')->nullable();
            $table->string('protein')->nullable();
            $table->string('fat')->nullable();
            $table->string('servings')->nullable();
            $table->string('total_time')->nullable();
            $table->string('is_featured')->nullable();
            $table->string('status')->nullable()->default('active');
            $table->text('ingredients')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_premium')->default(0)->comment('0-free, 1-premium')->nullable();
            $table->foreign('categorydiet_id')->references('id')->on('category_diets')->onDelete('cascade');
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
        Schema::dropIfExists('diets');
    }
}
