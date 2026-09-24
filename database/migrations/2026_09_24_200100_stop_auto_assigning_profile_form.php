<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El cuestionario "Perfil y Salud Inicial" dejó de ser necesario: casi todas
 * sus preguntas ya las recoge el registro / onboarding v2, ningún servicio ni
 * agente lee sus respuestas y 8 de 13 clientes lo tenían colgado como tarea
 * pendiente en Home.
 *
 *  - Deja de auto-asignarse a los clientes nuevos (auto_assign_all_clients = false).
 *  - Se desactivan SOLO las asignaciones sin enviar (active = false): dejan de
 *    salir en la app.
 *  - No se borra nada: el formulario, sus preguntas y las respuestas ya enviadas
 *    (form_submissions / form_answers) se conservan y siguen visibles en el admin.
 */
return new class extends Migration
{
    private const TITLE = 'Perfil y Salud Inicial';

    public function up(): void
    {
        $form = DB::table('forms')->where('title', self::TITLE)->first();
        if ($form === null) {
            return;
        }

        DB::table('forms')->where('id', $form->id)->update([
            'auto_assign_all_clients' => false,
            'updated_at'              => now(),
        ]);

        DB::table('form_assignments')
            ->where('form_id', $form->id)
            ->where('active', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('form_submissions')
                    ->whereColumn('form_submissions.form_assignment_id', 'form_assignments.id');
            })
            ->update(['active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Decisión de producto, no un cambio de esquema: no se reactiva solo.
    }
};
