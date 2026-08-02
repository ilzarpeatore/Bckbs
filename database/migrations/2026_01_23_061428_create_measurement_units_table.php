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
        Schema::create('measurement_units', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(); // Gram, Cup, Piece, Tablespoon
            $table->string('symbol')->nullable(); // g, cup, pc, tbsp
            $table->string('unit_type')->nullable()->comment('weight, volume, count');
            $table->double('base_conversion_factor')->default(1)->comment('Factor to convert to base unit (g or ml)');
            $table->boolean('is_standard')->default(false);
            $table->string('slug')->nullable();
            $table->string('status')->nullable()->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('measurement_units');
    }
};
