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
    // Nombre corto del índice único: el que genera Laravel por defecto
    // (subscription_payment_records_external_client_id_year_month_unique)
    // pasa de 64 caracteres y MySQL lo rechaza.
    private const UNIQUE = 'spr_external_client_year_month_unique';

    // Cada paso comprueba si ya está hecho: el primer despliegue falló a
    // mitad (MySQL no deshace el DDL) y dejó parte del esquema aplicado.
    public function up(): void
    {
        if (! Schema::hasTable('subscription_payment_clients')) {
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
        }

        Schema::table('subscription_payment_records', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        if (! Schema::hasColumn('subscription_payment_records', 'external_client_id')) {
            Schema::table('subscription_payment_records', function (Blueprint $table) {
                $table->unsignedBigInteger('external_client_id')->nullable()->after('user_id');
            });
        }

        $hasForeign = collect(Schema::getForeignKeys('subscription_payment_records'))
            ->contains(fn ($fk) => $fk['columns'] === ['external_client_id']);
        if (! $hasForeign) {
            Schema::table('subscription_payment_records', function (Blueprint $table) {
                $table->foreign('external_client_id')->references('id')->on('subscription_payment_clients')->onDelete('cascade');
            });
        }

        if (! Schema::hasIndex('subscription_payment_records', self::UNIQUE)) {
            Schema::table('subscription_payment_records', function (Blueprint $table) {
                $table->unique(['external_client_id', 'year', 'month'], self::UNIQUE);
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscription_payment_records', function (Blueprint $table) {
            $table->dropForeign(['external_client_id']);
            $table->dropUnique(self::UNIQUE);
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
