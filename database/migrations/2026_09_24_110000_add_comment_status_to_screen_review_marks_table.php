<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ScreenReviewFab (app): nueva opción "Añadir comentario" (status
     * 'comment') además de borrar / terminada / no entiendo -- pedido
     * explícito 2026-09-24. MySQL/MariaDB: ALTER del enum en crudo, mismo
     * estilo que el resto de migraciones MODIFY del proyecto.
     */
    public function up(): void
    {
        // "MODIFY ... ENUM" es solo MySQL/MariaDB: no-op en sqlite (entorno
        // de tests, phpunit.xml), mismo patrón que
        // 2026_08_13_172722_add_stripe_to_plan_subscriptions_payment_method_enum.
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE screen_review_marks MODIFY status ENUM('delete','done','confused','comment') NOT NULL");
    }

    public function down(): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('screen_review_marks')->where('status', 'comment')->update(['status' => 'confused']);
        DB::statement("ALTER TABLE screen_review_marks MODIFY status ENUM('delete','done','confused') NOT NULL");
    }
};
