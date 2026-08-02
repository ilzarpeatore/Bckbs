<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_body_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('metric_type');
            $table->decimal('value', 8, 2);
            $table->string('unit')->nullable();
            $table->timestamp('recorded_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'metric_type', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_body_metrics');
    }
};
