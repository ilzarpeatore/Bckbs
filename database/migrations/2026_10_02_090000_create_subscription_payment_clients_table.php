<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Clientes "externos" del seguimiento de pagos: personas que pagan
// mensualidades pero no tienen cuenta en la app (p. ej. clientes que solo
// estaban en Notion). Sus meses se guardan en subscription_payment_records
// con external_client_id en lugar de user_id.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payment_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->decimal('monthly_fee', 8, 2)->default(0);
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('subscription_payment_records', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unsignedBigInteger('external_client_id')->nullable()->after('user_id');

            $table->foreign('external_client_id')->references('id')->on('subscription_payment_clients')->onDelete('cascade');
            $table->unique(['external_client_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payment_records', function (Blueprint $table) {
            $table->dropForeign(['external_client_id']);
            $table->dropUnique(['external_client_id', 'year', 'month']);
            $table->dropColumn('external_client_id');
        });
        // user_id vuelve a NOT NULL solo si no quedan filas sin usuario.
        \Illuminate\Support\Facades\DB::table('subscription_payment_records')->whereNull('user_id')->delete();
        Schema::table('subscription_payment_records', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
        Schema::dropIfExists('subscription_payment_clients');
    }
};
