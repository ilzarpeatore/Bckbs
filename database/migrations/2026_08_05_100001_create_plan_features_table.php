<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('value');
            $table->unsignedSmallInteger('resettable_period')->default(0);
            $table->string('resettable_interval')->default('month');
            $table->unsignedMediumInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
