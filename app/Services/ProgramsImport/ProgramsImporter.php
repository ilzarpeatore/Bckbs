<?php

namespace App\Services\ProgramsImport;

use App\Models\BodyPart;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\ProgramDayAssignment;
use App\Models\TrainingProgram;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use App\Services\ExerciseMatcher\Dictionaries;
use App\Services\ExerciseMatcher\ExerciseMatcher;
use App\Services\ExerciseMatcher\Signature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa programas canónicos (salida de los adaptadores) a la BD:
 * training_programs + program_day_assignments + workout_templates
 * (+ bloques + ejercicios), resolviendo cada ejercicio de la fuente con el
 * ExerciseMatcher (niveles A-E). Los que no matcheen por encima del umbral
 * se crean automáticamente y se listan en el reporte.
 *
 * Idempotencia: si ya existe un TrainingProgram con el mismo
 * (source, source_id) se omite (skip) salvo --force.
 */
final class ProgramsImporter
{
    private ExerciseMatcher $matcher;
    private array $templateCache = [];   // hash => workout_template_id
    private array $report = [];          // filas del reporte CSV
    private array $stats = [
        'programs_created' => 0,
        'programs_skipped' => 0,
        'templates_created' => 0,
        'templates_reused' => 0,
        'exercises_matched' => 0,
        'exercises_created' => 0,
        'assignments' => 0,
    ];
    private array $levelCounts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'created' => 0];

    public function __construct(
        private readonly int $coachId = 1,
        private readonly int $numWeeks = 12,
        private readonly string $progressionMode = 'auto', // auto|none
        private readonly float $threshold = 0.72,
        private readonly bool $autoCreate = true,
        private readonly bool $dryRun = false,
        private readonly bool $force = false,
        private readonly bool $freeAccessible = false,
    ) {
        $this->matcher = new ExerciseMatcher($this->threshold);
    }

    public function report(): array
    {
        return $this->report;
    }

    public function stats(): array
    {
        return array_merge($this->stats, ['match_levels' => $this->levelCounts]);
    }

    /**
     * @param array $canonical  salida de un adaptador: ["programs" => [...]]
     * @return array<string,mixed> resumen por programa
     */
    public function import(array $canonical): array
    {
        $results = [];
        $programs = (array) ($canonical['programs'] ?? []);

        foreach ($programs as $program) {
            $results[] = $this->importProgram((array) $program);
        }

        return ['results' => $results, 'stats' => $this->stats()];
    }

    private function importProgram(array $program): array
    {
        $source = (string) ($program['source'] ?? 'unknown');
        $sourceId = (string) ($program['source_id'] ?? '');
        $title = trim((string) ($program['title'] ?? 'Programa importado'));
        $description = $program['description'] ?? null;
        $targetWeeks = $this->numWeeks > 0 ? $this->numWeeks : (int) ($program['num_weeks'] ?? 1);

        // Idempotencia
        $existing = TrainingProgram::where('source', $source)->where('source_id', $sourceId)->first();
        if ($existing !== null && !$this->force) {
            $this->stats['programs_skipped']++;

            return [
                'status' => 'skipped',
                'reason' => "ya existe training_program #{$existing->id} con source={$source}, source_id={$sourceId}",
                'title'  => $title,
            ];
        }

        // Construir las semanas completas
        $weeks = $this->buildWeeks($program, $targetWeeks);

        if ($this->dryRun) {
            $preview = $this->previewProgram($weeks, $title);

            return ['status' => 'dry-run', 'title' => $title, 'preview' => $preview];
        }

        return DB::transaction(function () use ($source, $sourceId, $title, $description, $weeks, $targetWeeks) {
            $trainingProgram = TrainingProgram::create([
                'title'              => $title,
                'workout_id'         => null,
                'coach_id'           => $this->coachId,
                'client_id'          => null,
                'num_weeks'          => $targetWeeks,
                'fecha_inicio'       => now()->toDateString(),
                'fecha_fin'          => now()->addWeeks($targetWeeks)->toDateString(),
                'activo'             => true,
                'is_personal'        => false,
                'personal_client_id' => null,
                'is_free_accessible' => $this->freeAccessible,
                'billing_plan_id'    => null,
                'source'             => $source,
                'source_id'          => $sourceId,
            ]);

            foreach ($weeks as $week) {
                foreach ($week['days'] as $day) {
                    $templateId = null;
                    if (!$day['is_rest']) {
                        $templateId = $this->materializeTemplate($day, $title, (int) $week['week_number']);
                    }
                    ProgramDayAssignment::create([
                        'training_program_id' => $trainingProgram->id,
                        'week_number'         => (int) $week['week_number'],
                        'day_of_week'         => (int) $day['day_of_week'],
                        'workout_template_id' => $templateId,
                        'scheduled_date'      => null,
                    ]);
                    $this->stats['assignments']++;
                }
            }

            $this->stats['programs_created']++;

            return [
                'status'              => 'created',
                'training_program_id' => $trainingProgram->id,
                'title'               => $title,
                'weeks'               => $targetWeeks,
                'assignments'         => count($weeks) * count($weeks[0]['days'] ?? []),
            ];
        });
    }

    /**
     * Expande/normaliza las semanas del programa al num_weeks objetivo.
     * - Si el programa trae todas las semanas explícitas (openweight program /
     *   wger con configs), se usan tal cual (ya llevan progresión).
     * - Si trae menos semanas que el objetivo (fuentes planas), la última se
     *   repite y, si progression=auto, se aplican los deltas de AutoProgression
     *   (carga/series/reps/RPE/RIR cambian semana a semana).
     */
    private function buildWeeks(array $program, int $targetWeeks): array
    {
        $sourceWeeks = (array) ($program['weeks'] ?? []);
        usort($sourceWeeks, fn ($a, $b) => ((int) ($a['week_number'] ?? 1)) <=> ((int) ($b['week_number'] ?? 1)));

        if ($sourceWeeks === []) {
            throw new \RuntimeException('El programa no trae semanas (weeks vacío).');
        }

        $explicitWeeks = count($sourceWeeks);
        $progressionTable = AutoProgression::table($targetWeeks);

        $weeks = [];
        for ($w = 1; $w <= $targetWeeks; $w++) {
            if ($w <= $explicitWeeks) {
                // semana explícita de la fuente: usar valores tal cual
                $base = $sourceWeeks[$w - 1];
                $weeks[] = [
                    'week_number' => $w,
                    'days'        => $this->expandDays((array) ($base['days'] ?? [])),
                ];
                continue;
            }

            // semana repetida desde la última explícita
            $base = $sourceWeeks[$explicitWeeks - 1];
            $days = $this->expandDays((array) ($base['days'] ?? []));

            if ($this->progressionMode === 'auto') {
                $progress = $progressionTable[$w - 1] ?? $progressionTable[array_key_last($progressionTable)];
                $days = $this->applyProgression($days, $progress);
            }

            $weeks[] = ['week_number' => $w, 'days' => $days];
        }

        return $weeks;
    }

    /** Rellena los huecos hasta el día 7 con descansos. */
    private function expandDays(array $days): array
    {
        $byDow = [];
        foreach ($days as $day) {
            $dow = (int) ($day['day_of_week'] ?? 0);
            if ($dow >= 1 && $dow <= 7) {
                $byDow[$dow] = $day;
            }
        }

        $out = [];
        for ($dow = 1; $dow <= 7; $dow++) {
            if (isset($byDow[$dow])) {
                $out[] = $byDow[$dow];
            } else {
                $out[] = [
                    'day_of_week' => $dow,
                    'title'       => 'Descanso',
                    'description' => null,
                    'is_rest'     => true,
                    'blocks'      => [],
                ];
            }
        }

        return $out;
    }

    /** Aplica deltas de progresión a todos los ejercicios de la semana. */
    private function applyProgression(array $days, array $progress): array
    {
        $load = (float) $progress['load_multiplier'];
        $setsDelta = (int) $progress['sets_delta'];
        $repsDelta = (int) $progress['reps_delta'];
        $rpeDelta = (float) $progress['rpe_delta'];
        $rirDelta = (float) $progress['rir_delta'];

        foreach ($days as $dIdx => $day) {
            $blocks = (array) ($day['blocks'] ?? []);
            foreach ($blocks as $bIdx => $block) {
                $exercises = (array) ($block['exercises'] ?? []);
                foreach ($exercises as $eIdx => $ex) {
                    if (($ex['load_kg'] ?? null) !== null) {
                        $ex['load_kg'] = round((float) $ex['load_kg'] * $load, 2);
                    }
                    if (($ex['sets'] ?? null) !== null) {
                        $ex['sets'] = max(1, (int) $ex['sets'] + $setsDelta);
                    }
                    foreach (['reps_min', 'reps_max'] as $k) {
                        if (($ex[$k] ?? null) !== null) {
                            $ex[$k] = max(1, (int) $ex[$k] + $repsDelta);
                        }
                    }
                    foreach (['rpe_min', 'rpe_max'] as $k) {
                        if (($ex[$k] ?? null) !== null) {
                            $ex[$k] = max(1.0, min(10.0, (float) $ex[$k] + $rpeDelta));
                        }
                    }
                    foreach (['rir_min', 'rir_max'] as $k) {
                        if (($ex[$k] ?? null) !== null) {
                            $ex[$k] = max(0.0, min(6.0, (float) $ex[$k] + $rirDelta));
                        }
                    }
                    $exercises[$eIdx] = $ex;
                }
                $block['exercises'] = $exercises;
                $blocks[$bIdx] = $block;
            }
            $day['blocks'] = $blocks;
            $days[$dIdx] = $day;
        }

        return $days;
    }

    /** Crea (o reutiliza si el contenido es idéntico) una workout_template. */
    private function materializeTemplate(array $day, string $programTitle, int $weekNumber): int
    {
        $hash = $this->templateHash($day);
        if (isset($this->templateCache[$hash])) {
            $this->stats['templates_reused']++;

            return $this->templateCache[$hash];
        }

        $template = WorkoutTemplate::create([
            'coach_id'     => $this->coachId,
            'title'        => sprintf('%s · %s (S%d)', $programTitle, $day['title'] ?? 'Día', $weekNumber),
            'description'  => $day['description'] ?? null,
            'is_exclusive' => false,
        ]);

        $order = 1;
        foreach ((array) ($day['blocks'] ?? []) as $block) {
            $blockModel = WorkoutTemplateBlock::create([
                'workout_template_id'        => $template->id,
                'source_section_template_id' => null,
                'title'                      => (string) ($block['title'] ?? 'Bloque'),
                'instructions'               => $block['instructions'] ?? null,
                'order'                      => $order++,
            ]);

            $sequence = 1;
            foreach ((array) ($block['exercises'] ?? []) as $ex) {
                $exerciseId = $this->resolveExercise((array) $ex);
                if ($exerciseId === null) {
                    continue;
                }

                WorkoutTemplateExercise::create([
                    'workout_template_block_id' => $blockModel->id,
                    'exercise_id'               => $exerciseId,
                    'sequence'                  => $sequence++,
                    'prescribed'                => $this->prescribed((array) $ex),
                    'enabled_metrics'           => $this->enabledMetrics((array) $ex),
                    'notes'                     => $ex['notes'] ?? null,
                ]);
            }
        }

        $this->templateCache[$hash] = $template->id;
        $this->stats['templates_created']++;

        return $template->id;
    }

    /**
     * Resuelve el ejercicio de la fuente a un id de nuestra BD:
     * matcher A-E; si no supera el umbral, se crea automáticamente
     * (auto-create) y se apunta al reporte.
     */
    private function resolveExercise(array $ex): ?int
    {
        $name = (string) ($ex['name'] ?? '');
        if ($name === '') {
            return null;
        }

        $sourceEquipment = $ex['equipment'] ?? null;
        $muscles = (array) ($ex['muscles'] ?? []);

        $match = $this->matcher->match($name, $sourceEquipment, $muscles, $sourceEquipment);

        if ($match !== null) {
            $this->stats['exercises_matched']++;
            $this->levelCounts[$match['level']]++;

            return (int) $match['exercise']->id;
        }

        // sin match: crear automáticamente
        if (!$this->autoCreate) {
            $this->reportRow($name, null, null, null, 'sin-match (auto-create desactivado)');

            return null;
        }

        $exercise = $this->createExercise($name, $sourceEquipment, $muscles, $ex);
        $this->stats['exercises_created']++;
        $this->levelCounts['created']++;

        return $exercise->id;
    }

    private function createExercise(string $name, ?string $equipment, array $muscles, array $ex): Exercise
    {
        // equipamiento: matchear contra tabla equipment (crear si falta)
        $equipmentId = null;
        $equipmentTitle = $equipment !== null ? Signature::cleanEquipment((string) $equipment) : Signature::detectEquipment(\App\Services\ExerciseMatcher\Normalizer::normalize($name));
        if ($equipmentTitle !== null) {
            $eq = Equipment::whereRaw('LOWER(title) = ?', [mb_strtolower($equipmentTitle)])->first();
            if ($eq === null) {
                $eq = Equipment::create(['title' => $equipmentTitle, 'status' => 'active']);
            }
            $equipmentId = $eq->id;
        }

        // body parts a partir del músculo detectado (señal del nombre o metadatos)
        $bodyPartIds = [];
        $candidates = $muscles;
        $sig = Signature::fromSource($name, $equipment, $muscles);
        if ($sig->muscle !== null) {
            $candidates[] = $sig->muscle;
        }
        foreach ($candidates as $m) {
            $key = Signature::muscleKey((string) $m);
            if ($key !== null && isset(Dictionaries::MUSCLE_BODY_PART[$key])) {
                $bodyPartIds[] = Dictionaries::MUSCLE_BODY_PART[$key];
            }
        }
        $bodyPartIds = array_values(array_unique($bodyPartIds));

        $title = Str::title($name);
        $slug = Str::slug($title);

        // evitar colisión de slug
        if (Exercise::withTrashed()->where('slug', $slug)->exists()) {
            $slug .= '-' . Str::lower(Str::random(6));
        }

        $exercise = Exercise::create([
            'title'          => $title,
            'slug'           => $slug,
            'instruction'    => $ex['notes'] ?? ('Ejercicio importado desde ' . ($ex['source_hint'] ?? 'fuente externa')),
            'tips'           => null,
            'video_type'     => null,
            'video_url'      => null,
            'bodypart_ids'   => $bodyPartIds !== [] ? $bodyPartIds : null,
            'duration'       => null,
            'sets'           => null,
            'equipment_id'   => $equipmentId,
            'level_id'       => 2,
            'status'         => 'active',
            'is_premium'     => 0,
            'based'          => 'reps',
            'type'           => 'sets',
            'seconds_per_rep' => null,
        ]);

        $this->reportRow($name, (int) $exercise->id, $title, null, 'creado');

        return $exercise;
    }

    /** Construye el JSON `prescribed` con claves en español (valores string). */
    private function prescribed(array $ex): array
    {
        $p = [];

        if (($ex['sets'] ?? null) !== null) {
            $p['series'] = (string) (int) $ex['sets'];
        }
        $repsMin = $ex['reps_min'] ?? null;
        $repsMax = $ex['reps_max'] ?? null;
        if ($repsMin !== null || $repsMax !== null) {
            $p['reps'] = ($repsMin !== null && $repsMax !== null && $repsMin != $repsMax)
                ? "{$repsMin}-{$repsMax}"
                : (string) ($repsMax ?? $repsMin);
        }
        if (($ex['load_kg'] ?? null) !== null) {
            $p['carga'] = (string) $ex['load_kg'];
        }
        if (($ex['weight_percent'] ?? null) !== null) {
            $p['carga_pct'] = (string) $ex['weight_percent'];
        }
        if (($ex['rest_sec'] ?? null) !== null) {
            $p['descanso'] = (string) (int) $ex['rest_sec'];
        }
        $rirMin = $ex['rir_min'] ?? null;
        $rirMax = $ex['rir_max'] ?? null;
        if ($rirMin !== null || $rirMax !== null) {
            $p['rir'] = ($rirMin !== null && $rirMax !== null && $rirMin != $rirMax)
                ? "{$rirMin}-{$rirMax}"
                : (string) ($rirMax ?? $rirMin);
        }
        $rpeMin = $ex['rpe_min'] ?? null;
        $rpeMax = $ex['rpe_max'] ?? null;
        if ($rpeMin !== null || $rpeMax !== null) {
            $p['rpe'] = ($rpeMin !== null && $rpeMax !== null && $rpeMin != $rpeMax)
                ? "{$rpeMin}-{$rpeMax}"
                : (string) ($rpeMax ?? $rpeMin);
        }
        if (($ex['tempo'] ?? null) !== null) {
            $p['tempo'] = (string) $ex['tempo'];
        }
        if (($ex['duration_sec'] ?? null) !== null) {
            $p['duracion'] = (string) (int) $ex['duration_sec'];
        }

        return $p;
    }

    /** enabled_metrics según los campos presentes (como los existentes en BD). */
    private function enabledMetrics(array $ex): array
    {
        $metrics = [];
        if (($ex['reps_min'] ?? null) !== null || ($ex['reps_max'] ?? null) !== null) {
            $metrics[] = 'reps';
        }
        if (($ex['load_kg'] ?? null) !== null || ($ex['weight_percent'] ?? null) !== null) {
            $metrics[] = 'carga';
        }
        if (($ex['rest_sec'] ?? null) !== null) {
            $metrics[] = 'descanso';
        }
        if (($ex['rir_min'] ?? null) !== null || ($ex['rir_max'] ?? null) !== null) {
            $metrics[] = 'rir';
        }
        if (($ex['rpe_min'] ?? null) !== null || ($ex['rpe_max'] ?? null) !== null) {
            $metrics[] = 'rpe';
        }
        if (($ex['tempo'] ?? null) !== null) {
            $metrics[] = 'tempo';
        }
        if (($ex['duration_sec'] ?? null) !== null) {
            $metrics[] = 'duracion';
        }

        return $metrics !== [] ? $metrics : ['reps', 'carga', 'descanso', 'rir', 'rpe'];
    }

    private function templateHash(array $day): string
    {
        $canonical = [
            'title'  => $day['title'] ?? null,
            'blocks' => array_map(fn ($b) => [
                'title'        => $b['title'] ?? null,
                'instructions' => $b['instructions'] ?? null,
                'exercises'    => array_map(fn ($e) => [
                    'name'           => mb_strtolower(trim((string) ($e['name'] ?? ''))),
                    'sets'           => $e['sets'] ?? null,
                    'reps_min'       => $e['reps_min'] ?? null,
                    'reps_max'       => $e['reps_max'] ?? null,
                    'rest_sec'       => $e['rest_sec'] ?? null,
                    'rpe_min'        => $e['rpe_min'] ?? null,
                    'rpe_max'        => $e['rpe_max'] ?? null,
                    'rir_min'        => $e['rir_min'] ?? null,
                    'rir_max'        => $e['rir_max'] ?? null,
                    'load_kg'        => $e['load_kg'] ?? null,
                    'weight_percent' => $e['weight_percent'] ?? null,
                    'tempo'          => $e['tempo'] ?? null,
                    'duration_sec'   => $e['duration_sec'] ?? null,
                ], (array) ($b['exercises'] ?? [])),
            ], (array) ($day['blocks'] ?? [])),
        ];

        return sha1((string) json_encode($canonical));
    }

    /** Vista previa en dry-run: estructura + matches sin escribir BD. */
    private function previewProgram(array $weeks, string $title): array
    {
        $preview = ['title' => $title, 'weeks' => []];
        foreach ($weeks as $week) {
            $wPreview = ['week_number' => $week['week_number'], 'days' => []];
            foreach ($week['days'] as $day) {
                $dPreview = [
                    'day_of_week' => $day['day_of_week'],
                    'title'       => $day['title'] ?? null,
                    'is_rest'     => (bool) ($day['is_rest'] ?? false),
                    'exercises'   => [],
                ];
                foreach ((array) ($day['blocks'] ?? []) as $block) {
                    foreach ((array) ($block['exercises'] ?? []) as $ex) {
                        $match = $this->matcher->match(
                            (string) ($ex['name'] ?? ''),
                            $ex['equipment'] ?? null,
                            (array) ($ex['muscles'] ?? []),
                            $ex['equipment'] ?? null,
                        );
                        if ($match === null) {
                            $cands = $this->matcher->topCandidates(
                                (string) ($ex['name'] ?? ''),
                                $ex['equipment'] ?? null,
                                (array) ($ex['muscles'] ?? []),
                                $ex['equipment'] ?? null,
                                3,
                            );
                            $candStr = implode('; ', array_map(
                                fn ($c) => "#{$c['exercise']->id} {$c['exercise']->title} ({$c['level']}, {$c['confidence']})",
                                $cands,
                            ));
                            $this->reportRow((string) ($ex['name'] ?? ''), null, null, $candStr !== '' ? $candStr : null, 'crearía (dry-run)');
                        }
                        $dPreview['exercises'][] = [
                            'source'     => $ex['name'] ?? '',
                            'match'      => $match !== null ? $match['exercise']->title : null,
                            'match_id'   => $match !== null ? $match['exercise']->id : null,
                            'level'      => $match['level'] ?? 'created',
                            'confidence' => $match['confidence'] ?? null,
                            'prescribed' => $this->prescribed((array) $ex),
                        ];
                    }
                }
                $wPreview['days'][] = $dPreview;
            }
            $preview['weeks'][] = $wPreview;
        }

        return $preview;
    }

    private function reportRow(string $sourceName, ?int $exerciseId, ?string $createdTitle, ?string $candidates, string $action): void
    {
        // deduplicar por ejercicio fuente (la misma fuente aparece en varias semanas)
        foreach ($this->report as $row) {
            if (mb_strtolower($row['source_exercise']) === mb_strtolower($sourceName)) {
                return;
            }
        }

        $this->report[] = [
            'source_exercise' => $sourceName,
            'exercise_id'     => $exerciseId,
            'resolved_title'  => $createdTitle,
            'candidates'      => $candidates,
            'action'          => $action,
        ];
    }
}
