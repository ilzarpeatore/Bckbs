<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopping_list_id');
            $table->unsignedBigInteger('ingredient_id')->nullable();
            $table->string('custom_item_name')->nullable();
            $table->double('total_grams')->nullable();
            $table->double('display_quantity')->nullable();
            $table->unsignedBigInteger('measurement_unit_id')->nullable();
            $table->boolean('is_checked')->default(0);
            $table->boolean('manually_added')->default(0);
            $table->timestamps();

            $table->foreign('shopping_list_id')->references('id')->on('shopping_lists')->onDelete('cascade');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('set null');
            $table->foreign('measurement_unit_id')->references('id')->on('measurement_units')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
    }
};
