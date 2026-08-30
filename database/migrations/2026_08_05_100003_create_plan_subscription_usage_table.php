<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_subscription_usage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained('plan_subscriptions')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('plan_features')->cascadeOnDelete();
            $table->unsignedSmallInteger('used')->default(0);
            $table->string('timezone')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_subscription_usage');
    }
};
