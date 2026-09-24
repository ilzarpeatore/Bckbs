<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\User;
use Illuminate\Database\Seeder;

class StandardProfileFormSeeder extends Seeder
{
    /**
     * (DESACTIVADO desde 2026-09-24, ver más abajo `auto_assign_all_clients`.)
     * Cuestionario fijo "Perfil y Salud Inicial": se auto-asignaba a TODO
     * cliente nuevo en el registro (ver UserController::register()).
     * No es un formulario ad-hoc que un coach asigna manualmente uno por
     * uno — está marcado con `auto_assign_all_clients = true`.
     *
     * Idempotente: usa updateOrCreate/firstOrCreate por título, así que
     * correr el seeder varias veces no duplica el formulario ni sus
     * preguntas (las preguntas se re-sincronizan por `question_text`).
     *
     * IMPORTANTE: los `question_text` de "Nivel de experiencia", "Equipamiento
     * disponible" y "Frecuencia de entrenamiento" son leídos textualmente
     * desde el cliente Flutter (lib/screens/onboarding/profile_setup_form_screen.dart)
     * para pre-rellenar esas 3 respuestas con lo que el usuario ya eligió
     * durante el registro (register_flow_screen.dart). Si cambias este
     * texto aquí, actualiza también las constantes `_kExperienceQuestion`,
     * `_kEquipmentQuestion` y `_kFrequencyQuestion` en ese archivo Dart.
     */
    public function run(): void
    {
        // El Form requiere coach_id (FK not-null). Usamos el System Admin
        // sembrado por UserTableSeeder como "propietario" del formulario
        // estándar de la app (no es un formulario de un coach concreto).
        $ownerId = User::where('user_type', 'admin')->value('id')
            ?? User::query()->value('id');

        if (!$ownerId) {
            // No hay ningún usuario todavía (seeder corriendo antes que
            // UserTableSeeder) — no podemos crear el Form sin coach_id.
            return;
        }

        $form = Form::updateOrCreate(
            ['title' => 'Perfil y Salud Inicial'],
            [
                'coach_id' => $ownerId,
                'description' => 'Formulario estándar de bienvenida: datos personales, de salud y de entrenamiento. Se completa una sola vez tras el registro.',
                'recurrence' => null, // questionnaire (no recurrente)
                // 2026-09-24: YA NO se auto-asigna. Casi todas sus preguntas las recoge el
                // registro/onboarding v2 (medicamentos y suplementos pasaron al cuestionario de
                // nutrición) y ningún servicio lee sus respuestas. El formulario se conserva
                // solo por las respuestas históricas. Ver la migración
                // 2026_09_24_200100_stop_auto_assigning_profile_form.
                'auto_assign_all_clients' => false,
            ]
        );

        $questions = [
            ['question_text' => 'Nombre completo', 'type' => 'text', 'is_required' => true, 'order' => 0],
            ['question_text' => 'Género', 'type' => 'multiple_choice', 'options' => ['Masculino', 'Femenino', 'Otro'], 'allow_multiple' => false, 'is_required' => true, 'order' => 1],
            ['question_text' => 'Fecha de nacimiento', 'type' => 'date', 'is_required' => true, 'order' => 2],
            ['question_text' => 'Número de teléfono', 'type' => 'text', 'placeholder' => '+34 600 000 000', 'is_required' => false, 'order' => 3],
            ['question_text' => 'Dirección', 'type' => 'text', 'is_required' => false, 'order' => 4],
            ['question_text' => 'Nacionalidad', 'type' => 'text', 'is_required' => false, 'order' => 5],
            ['question_text' => 'Dirección (calle)', 'type' => 'text', 'is_required' => false, 'order' => 6],
            ['question_text' => 'Apartamento / Suite', 'type' => 'text', 'is_required' => false, 'order' => 7],
            ['question_text' => 'Estado / Provincia', 'type' => 'text', 'is_required' => false, 'order' => 8],
            ['question_text' => 'Código postal', 'type' => 'text', 'is_required' => false, 'order' => 9],
            ['question_text' => 'Alergias', 'type' => 'textarea', 'placeholder' => 'Ej: Polen, frutos secos (separa con comas)', 'is_required' => false, 'order' => 10],
            ['question_text' => 'Medicamentos', 'type' => 'textarea', 'placeholder' => 'Ej: Aspirina (separa con comas)', 'is_required' => false, 'order' => 11],
            ['question_text' => 'Suplementos', 'type' => 'textarea', 'placeholder' => 'Ej: BCAAs, creatina (separa con comas)', 'is_required' => false, 'order' => 12],
            ['question_text' => 'Altura (cm)', 'type' => 'number', 'is_required' => true, 'order' => 13],
            ['question_text' => 'Peso (kg)', 'type' => 'number', 'is_required' => true, 'order' => 14],
            // Migradas desde la antigua FitnessAssessmentScreen (fusionada en RegisterFlowScreen):
            ['question_text' => 'Nivel de experiencia entrenando', 'type' => 'multiple_choice', 'options' => ['Principiante', 'Intermedio', 'Avanzado', 'Experto'], 'allow_multiple' => false, 'is_required' => true, 'order' => 15],
            ['question_text' => 'Equipamiento disponible', 'type' => 'multiple_choice', 'options' => ['Solo peso corporal', 'Mancuernas', 'Gimnasio completo', 'Solo máquinas', 'Kettlebells'], 'allow_multiple' => false, 'is_required' => true, 'order' => 16],
            ['question_text' => 'Frecuencia de entrenamiento (días/semana)', 'type' => 'scale', 'scale_max' => 7, 'is_required' => true, 'order' => 17],
            ['question_text' => 'Nota adicional', 'type' => 'textarea', 'is_required' => false, 'order' => 18],
        ];

        foreach ($questions as $q) {
            FormQuestion::updateOrCreate(
                ['form_id' => $form->id, 'question_text' => $q['question_text']],
                array_merge([
                    'options' => null,
                    'max_files' => null,
                    'metric_id' => null,
                    'sync_type' => null,
                    'allow_multiple' => false,
                    'placeholder' => null,
                    'scale_max' => 10,
                    'star_max' => 5,
                ], $q)
            );
        }
    }
}
