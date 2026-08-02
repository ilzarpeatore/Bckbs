<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda de idempotencia: evita que PackageFulfillmentService importe el
     * contenido del Package dos veces si la Subscription se guarda más de una
     * vez estando ya activa+pagada (ej. una edición posterior desde el admin).
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('fulfilled_at')->nullable()->after('subscription_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('fulfilled_at');
        });
    }
};
