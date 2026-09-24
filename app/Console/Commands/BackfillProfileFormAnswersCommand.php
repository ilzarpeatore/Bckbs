<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Traslada a su sitio definitivo lo que los clientes ya contestaron en el
 * cuestionario "Perfil y Salud Inicial" (que se deja de asignar), sin borrar
 * nada de las respuestas originales:
 *
 *   - "Número de teléfono"      -> users.phone_number            (solo si está vacío)
 *   - "Dirección" (o calle...)  -> user_profiles.address         (solo si existe el perfil y está vacío)
 *   - "Medicamentos"/"Suplementos" -> nutrition_questionnaire_answers.medications/supplements
 *                                   (solo si el cliente tiene ya esa fila y el campo está vacío)
 *
 *   php artisan forms:backfill-profile-answers          (dry-run: solo muestra)
 *   php artisan forms:backfill-profile-answers --apply  (aplica, en una transacción)
 *
 * Idempotente: nunca pisa un dato ya rellenado.
 */
class BackfillProfileFormAnswersCommand extends Command
{
    protected $signature = 'forms:backfill-profile-answers {--apply : Aplicar los cambios (por defecto solo muestra qué haría)}';

    protected $description = 'Copia teléfono, dirección, medicamentos y suplementos del formulario "Perfil y Salud Inicial" a su sitio definitivo';

    private const TITLE = 'Perfil y Salud Inicial';

    public function handle(): int
    {
        $form = DB::table('forms')->where('title', self::TITLE)->first();
        if ($form === null) {
            $this->info('No existe el formulario; nada que hacer.');

            return self::SUCCESS;
        }

        $rows = DB::table('form_answers as fans')
            ->join('form_questions as fq', 'fq.id', '=', 'fans.form_question_id')
            ->join('form_submissions as fs', 'fs.id', '=', 'fans.form_submission_id')
            ->join('form_assignments as fa', 'fa.id', '=', 'fs.form_assignment_id')
            ->where('fq.form_id', $form->id)
            ->orderBy('fs.submitted_at')
            ->get(['fa.client_id', 'fq.question_text', 'fans.answer_value']);

        $byClient = [];
        foreach ($rows as $r) {
            $value = trim((string) $r->answer_value);
            if ($value !== '') {
                $byClient[$r->client_id][$r->question_text] = $value; // la última respuesta gana
            }
        }

        $apply = (bool) $this->option('apply');
        $actions = [];

        foreach ($byClient as $clientId => $a) {
            if (isset($a['Número de teléfono'])) {
                $current = DB::table('users')->where('id', $clientId)->value('phone_number');
                if ($current === null || trim((string) $current) === '') {
                    $actions[] = ['users', $clientId, 'phone_number', $a['Número de teléfono']];
                }
            }

            // "Dirección" tal cual; si no, se compone solo cuando hay calle (un código postal suelto no es una dirección).
            $address = $a['Dirección'] ?? (isset($a['Dirección (calle)']) ? implode(', ', array_filter([
                $a['Dirección (calle)'], $a['Apartamento / Suite'] ?? null,
                $a['Código postal'] ?? null, $a['Estado / Provincia'] ?? null,
            ])) : '');
            if ($address !== '') {
                $profile = DB::table('user_profiles')->where('user_id', $clientId)->first();
                if ($profile !== null && trim((string) $profile->address) === '') {
                    $actions[] = ['user_profiles', $clientId, 'address', $address];
                }
            }

            foreach (['Medicamentos' => 'medications', 'Suplementos' => 'supplements'] as $question => $column) {
                if (!isset($a[$question])) {
                    continue;
                }
                $row = DB::table('nutrition_questionnaire_answers')->where('user_id', $clientId)->first();
                if ($row !== null && trim((string) ($row->{$column} ?? '')) === '') {
                    $actions[] = ['nutrition_questionnaire_answers', $clientId, $column, $a[$question]];
                }
            }
        }

        if ($actions === []) {
            $this->info('Nada que trasladar (todo ya está en su sitio o no hay respuestas).');

            return self::SUCCESS;
        }

        foreach ($actions as [$table, $clientId, $column, $value]) {
            $this->line(sprintf('%s cliente #%d  %s.%s = "%s"', $apply ? 'SET' : 'set', $clientId, $table, $column, mb_strimwidth($value, 0, 60, '…')));
        }

        if (!$apply) {
            $this->info(count($actions).' cambio(s) pendientes. Ejecuta con --apply para aplicarlos.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($actions) {
            foreach ($actions as [$table, $clientId, $column, $value]) {
                DB::table($table)->where($table === 'users' ? 'id' : 'user_id', $clientId)->update([$column => $value]);
            }
        });
        $this->info(count($actions).' cambio(s) aplicados.');

        return self::SUCCESS;
    }
}
