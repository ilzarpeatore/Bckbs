<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Seguimiento manual de pagos de suscripción mensual (panel de Informes,
// pestaña "Seguimiento de pagos"). Una fila = un mes concreto para un
// cliente concreto -- solo existe si el coach ya marcó ese mes (pagado o
// con un importe ajustado). Un mes sin fila se interpreta en frontend
// como "no pagado, importe = tarifa actual del cliente" (users.monthly_fee).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payment_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 8, 2);
            $table->boolean('paid')->default(false);
            $table->date('paid_at')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_records');
    }
};
