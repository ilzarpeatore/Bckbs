<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_subscriptions', function (Blueprint $table) {
            $table->enum('payment_method', ['bizum', 'efectivo', 'transferencia', 'otro'])->nullable()->after('payment_status');
            $table->text('payment_notes')->nullable()->after('payment_method');
            $table->unsignedInteger('amount_paid_cents')->nullable()->after('payment_notes')->comment('Importe real cobrado en céntimos');
        });
    }

    public function down(): void
    {
        Schema::table('plan_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_notes', 'amount_paid_cents']);
        });
    }
};
