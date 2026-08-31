<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('subscriber');
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('timezone')->nullable();
            $table->decimal('total_amount')->default('0.00');
            $table->string('payment_status')->default('pending');
            $table->string('payment_type')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('access_revoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subscriber_type', 'subscriber_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_subscriptions');
    }
};
