<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('price')->default('0.00');
            $table->decimal('signup_fee')->default('0.00');
            $table->string('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('trial_period')->default(0);
            $table->string('trial_interval')->default('day');
            $table->unsignedSmallInteger('invoice_period')->default(0);
            $table->string('invoice_interval')->default('month');
            $table->unsignedSmallInteger('grace_period')->default(0);
            $table->string('grace_interval')->default('day');
            $table->unsignedTinyInteger('prorate_day')->nullable();
            $table->unsignedTinyInteger('prorate_period')->nullable();
            $table->unsignedTinyInteger('prorate_extend_due')->nullable();
            $table->unsignedSmallInteger('active_subscribers_limit')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('training_program_id')->nullable();
            $table->unsignedBigInteger('meal_plan_template_id')->nullable();
            $table->boolean('grants_full_workout_library')->default(false);
            $table->boolean('grants_full_recipe_library')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
