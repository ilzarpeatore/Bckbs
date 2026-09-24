<?php

namespace App\Services;

use App\Models\ProgramDayAssignment;
use App\Models\TrainingProgram;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Editor de sesiones a nivel programa ("matriz"): para cada tipo de sesión de
 * un programa (Empuje, Torso A, ...) devuelve todas sus apariciones a lo largo
 * de las semanas como columnas y la unión de sus ejercicios como filas, y
 * aplica en lote los cambios hechos sobre esa matriz.
 *
 * Una misma workout_template puede estar asignada a varias semanas/días
 * (los imports reutilizan la plantilla cuando dos semanas prescriben lo
 * mismo). Editar una de esas columnas NO debe cambiar las demás: antes de
 * aplicar un cambio sobre una asignación cuya plantilla es compartida, se
 * clona la plantilla solo para esa asignación (WorkoutTemplate::cloneStructure).
 */
class ProgramSessionMatrixService
{
    /** Claves de `prescribed` que se editan desde la matriz. */
    public const EDITABLE_KEYS = ['series', 'reps', 'carga', 'rir', 'rpe', 'descanso', 'tempo', 'duracion'];

    /**
     * Métricas opcionales que, si se rellena su valor, deben estar habilitadas en `enabled_metrics`
     * del ejercicio para que la app las muestre (si no, el valor existe pero el cliente no lo ve).
     */
    private const METRICS_ENABLED_BY_VALUE = ['tempo', 'duracion'];

    /**
     * Clave y etiqueta del "tipo de sesión" a partir del título de la plantilla:
     * "Programa · Torso A (Empuje/Traccion) (S2)" -> "Torso A (Empuje/Traccion)".
     * El "(S#)" final es la variante de progresión, no la semana.
     *
     * @return array{0:string,1:string} [clave normalizada, etiqueta]
     */
    public static function sessionStem(?string $title): array
    {
        $original = trim((string) $title);
        $t = $original;

        $pos = mb_strrpos($t, ' · ');
        if ($pos !== false && $pos > 0) {
            $t = trim(mb_substr($t, $pos + mb_strlen(' · ')));
        }

        $t = trim(preg_replace('/\s*\(S\d+\)\s*$/iu', '', $t));
        if ($t === '') {
            $t = $original !== '' ? $original : 'Sin título';
        }

        return [mb_strtolower($t), $t];
    }

    /** Clave de fila: mismo ejercicio + nº de aparición dentro de la sesión (un ejercicio puede repetirse). */
    private static function rowKey(int $exerciseId, int $occurrence): string
    {
        return $exerciseId.'#'.$occurrence;
    }

    /**
     * @param  array<int>|null  $assignmentIds  si se pasa, solo los tipos de sesión de esas asignaciones
     */
    public function build(TrainingProgram $program, ?array $assignmentIds = null): array
    {
        $assignments = ProgramDayAssignment::where('training_program_id', $program->id)
            ->whereNotNull('workout_template_id')
            ->with(['workoutTemplate.blocks.exercises.exercise'])
            ->orderBy('week_number')
            ->orderBy('day_of_week')
            ->orderBy('id')
            ->get()
            ->filter(fn ($a) => $a->workoutTemplate !== null);

        // Cuántas asignaciones (de cualquier programa) usan cada plantilla, para marcar las vinculadas.
        $templateIds = $assignments->pluck('workout_template_id')->unique()->values()->all();
        $usage = ProgramDayAssignment::whereIn('workout_template_id', $templateIds)
            ->get(['id', 'training_program_id', 'workout_template_id', 'week_number'])
            ->groupBy('workout_template_id');

        $slots = [];
        foreach ($assignments as $a) {
            [$key, $label] = self::sessionStem($a->workoutTemplate->title);
            $slots[$key]['key'] = $key;
            $slots[$key]['label'] = $label;
            $slots[$key]['assignments'][] = $a;
        }

        if ($assignmentIds !== null) {
            $wanted = array_flip(array_map('intval', $assignmentIds));
            $slots = array_filter($slots, function ($slot) use ($wanted) {
                foreach ($slot['assignments'] as $a) {
                    if (isset($wanted[$a->id])) {
                        return true;
                    }
                }
                return false;
            });
        }

        $out = [];
        foreach ($slots as $slot) {
            $out[] = $this->buildSlot($slot, $usage);
        }

        return [
            'program' => [
                'id'        => $program->id,
                'title'     => $program->title,
                'num_weeks' => $program->num_weeks,
                // Clientes que usan ESTE programa de biblioteca directamente (sin copia propia): editar
                // aquí cambia su calendario en vivo. Vacío en copias de cliente y programas sin asignar.
                'direct_clients' => TemplateIsolationGuard::directClientsOfProgram($program),
            ],
            'slots' => $out,
        ];
    }

    private function buildSlot(array $slot, $usage): array
    {
        $columns = [];
        $exerciseKeysByColumn = [];
        $cells = [];
        $rowMeta = [];

        foreach ($slot['assignments'] as $a) {
            $template = $a->workoutTemplate;
            $sharedWith = $usage->get($a->workout_template_id, collect())
                ->where('id', '!=', $a->id);

            $columns[] = [
                'assignment_id'        => $a->id,
                'week_number'          => (int) $a->week_number,
                'day_of_week'          => (int) $a->day_of_week,
                'is_deload'            => (bool) $a->is_deload,
                'scheduled_date'       => $a->scheduled_date?->toDateString(),
                'workout_template_id'  => $a->workout_template_id,
                'template_title'       => $template->title,
                'linked_weeks'         => $sharedWith->where('training_program_id', $a->training_program_id)
                                            ->pluck('week_number')->unique()->sort()->values()->all(),
                'linked_elsewhere'     => $sharedWith->where('training_program_id', '!=', $a->training_program_id)->count(),
            ];

            $occurrences = [];
            $order = [];
            foreach ($template->blocks as $block) {
                foreach ($block->exercises as $ex) {
                    $occ = $occurrences[$ex->exercise_id] = ($occurrences[$ex->exercise_id] ?? 0) + 1;
                    $rk = self::rowKey((int) $ex->exercise_id, $occ);
                    $order[] = $rk;
                    $cells[$rk][$a->id] = [
                        'id'              => $ex->id,
                        'block_id'        => $block->id,
                        'sequence'        => (int) $ex->sequence,
                        'prescribed'      => $ex->prescribed ?? (object) [],
                        'enabled_metrics' => $ex->enabled_metrics ?? [],
                        'notes'           => $ex->notes,
                    ];
                    if (!isset($rowMeta[$rk])) {
                        $rowMeta[$rk] = [
                            'exercise_id'    => (int) $ex->exercise_id,
                            'exercise_title' => $ex->exercise?->title ?? ('Ejercicio #'.$ex->exercise_id),
                            'block_title'    => $block->title,
                        ];
                    }
                }
            }
            $exerciseKeysByColumn[$a->id] = $order;
        }

        // Unión ordenada de filas: cada ejercicio nuevo se inserta detrás de su predecesor en su sesión.
        $rowOrder = [];
        foreach ($exerciseKeysByColumn as $order) {
            $prev = null;
            foreach ($order as $rk) {
                if (!in_array($rk, $rowOrder, true)) {
                    $at = $prev === null ? 0 : array_search($prev, $rowOrder, true) + 1;
                    array_splice($rowOrder, $at, 0, [$rk]);
                }
                $prev = $rk;
            }
        }

        $rows = [];
        foreach ($rowOrder as $rk) {
            $rows[] = array_merge(['row_key' => $rk], $rowMeta[$rk], [
                'cells' => (object) ($cells[$rk] ?? []),
            ]);
        }

        return [
            'key'     => $slot['key'],
            'label'   => $slot['label'],
            'columns' => $columns,
            'rows'    => $rows,
        ];
    }

    /**
     * Aplica en lote los cambios de la matriz. Devuelve un resumen.
     *
     * @param  array<int,array<string,mixed>>  $changes
     * @param  array<int,array{week_number:int,is_deload:bool}>  $deload
     */
    public function save(TrainingProgram $program, int $coachId, array $changes, array $deload = []): array
    {
        return DB::transaction(function () use ($program, $coachId, $changes, $deload) {
            $unlinked = [];
            $applied = ['update' => 0, 'add' => 0, 'remove' => 0, 'substitute' => 0, 'reorder' => 0];
            // assignment_id => [old row id => new row id, 'blocks' => [old block id => new block id]]
            $remap = [];

            $byAssignment = [];
            foreach ($changes as $i => $change) {
                $byAssignment[(int) ($change['assignment_id'] ?? 0)][] = $change;
            }

            foreach ($byAssignment as $assignmentId => $assignmentChanges) {
                $assignment = ProgramDayAssignment::where('training_program_id', $program->id)->find($assignmentId);
                if ($assignment === null || $assignment->workout_template_id === null) {
                    throw ValidationException::withMessages(['changes' => "La sesión $assignmentId no pertenece a este programa."]);
                }

                $template = WorkoutTemplate::where('coach_id', $coachId)
                    ->with('blocks.exercises')
                    ->find($assignment->workout_template_id);
                if ($template === null) {
                    throw ValidationException::withMessages(['changes' => "No tienes permiso sobre la plantilla de la sesión $assignmentId."]);
                }

                // Plantilla compartida -> clonar solo para esta asignación antes de tocarla.
                $sharedCount = ProgramDayAssignment::where('workout_template_id', $template->id)->count();
                if ($sharedCount > 1 || TemplateIsolationGuard::isSharedAcrossOwners((int) $template->id)) {
                    $clone = $template->cloneStructure();
                    $clone->load('blocks.exercises');
                    $assignment->update(['workout_template_id' => $clone->id]);

                    $map = ['blocks' => []];
                    foreach ($template->blocks->values() as $bi => $block) {
                        $newBlock = $clone->blocks->values()->get($bi);
                        if ($newBlock === null) {
                            continue;
                        }
                        $map['blocks'][$block->id] = $newBlock->id;
                        foreach ($block->exercises->values() as $ei => $ex) {
                            $newEx = $newBlock->exercises->values()->get($ei);
                            if ($newEx !== null) {
                                $map[$ex->id] = $newEx->id;
                            }
                        }
                    }
                    $remap[$assignmentId] = $map;
                    $unlinked[] = [
                        'assignment_id'   => $assignmentId,
                        'old_template_id' => $template->id,
                        'new_template_id' => $clone->id,
                    ];
                    $template = $clone;
                }

                foreach ($assignmentChanges as $change) {
                    $this->applyChange($template, $assignmentId, $change, $remap[$assignmentId] ?? null, $applied);
                }
            }

            foreach ($deload as $d) {
                ProgramDayAssignment::where('training_program_id', $program->id)
                    ->where('week_number', (int) $d['week_number'])
                    ->update(['is_deload' => (bool) $d['is_deload']]);
            }

            return ['applied' => $applied, 'unlinked' => $unlinked];
        });
    }

    private function applyChange(WorkoutTemplate $template, int $assignmentId, array $change, ?array $map, array &$applied): void
    {
        $type = $change['type'] ?? null;
        $mapRow = fn ($id) => $map[(int) $id] ?? (int) $id;

        if ($type === 'add') {
            $blockId = isset($change['block_id']) ? (int) ($map['blocks'][(int) $change['block_id']] ?? $change['block_id']) : null;
            $block = $blockId
                ? WorkoutTemplateBlock::where('workout_template_id', $template->id)->find($blockId)
                : null;
            $block ??= WorkoutTemplateBlock::where('workout_template_id', $template->id)->orderByDesc('order')->first();
            $block ??= WorkoutTemplateBlock::create([
                'workout_template_id' => $template->id,
                'title'               => 'Parte principal',
                'order'               => 1,
            ]);

            $sequence = (WorkoutTemplateExercise::where('workout_template_block_id', $block->id)->max('sequence') ?? 0) + 1;
            $prescribed = $this->cleanPrescribed($change['prescribed'] ?? [], []);
            $metrics = $this->cleanMetrics($change['enabled_metrics'] ?? null) ?? ['reps', 'carga', 'descanso', 'rir'];
            WorkoutTemplateExercise::create([
                'workout_template_block_id' => $block->id,
                'exercise_id'               => (int) $change['exercise_id'],
                'sequence'                  => $sequence,
                'prescribed'                => $prescribed,
                'enabled_metrics'           => $this->withMetricsForValues($metrics, $prescribed),
                'notes'                     => $this->cleanNotes($change['notes'] ?? null),
            ]);
            $applied['add']++;
            return;
        }

        if ($type === 'reorder') {
            $this->reorder($template, $assignmentId, (array) ($change['order'] ?? []), $map);
            $applied['reorder']++;
            return;
        }

        $row = WorkoutTemplateExercise::whereHas('block', fn ($q) => $q->where('workout_template_id', $template->id))
            ->find($mapRow($change['row_id'] ?? 0));
        if ($row === null) {
            throw ValidationException::withMessages(['changes' => 'Un ejercicio a modificar no pertenece a la sesión '.$assignmentId.'.']);
        }

        if ($type === 'remove') {
            $row->delete();
            $applied['remove']++;
        } elseif ($type === 'substitute') {
            $row->update(['exercise_id' => (int) $change['exercise_id']]);
            $applied['substitute']++;
        } elseif ($type === 'update') {
            $data = [];
            if (array_key_exists('prescribed', $change)) {
                $data['prescribed'] = $this->cleanPrescribed($change['prescribed'] ?? [], $row->prescribed ?? []);
            }
            if (array_key_exists('enabled_metrics', $change)) {
                $metrics = $this->cleanMetrics($change['enabled_metrics']);
                if ($metrics !== null) {
                    $data['enabled_metrics'] = $metrics;
                }
            }
            // Un valor de tempo/duración solo lo ve el cliente si la métrica está habilitada.
            if (isset($data['prescribed'])) {
                $withValues = $this->withMetricsForValues($data['enabled_metrics'] ?? ($row->enabled_metrics ?? []), $data['prescribed']);
                if ($withValues !== ($row->enabled_metrics ?? [])) {
                    $data['enabled_metrics'] = $withValues;
                }
            }
            if (array_key_exists('notes', $change)) {
                $data['notes'] = $this->cleanNotes($change['notes']);
            }
            if ($data !== []) {
                $row->update($data);
            }
            $applied['update']++;
        } else {
            throw ValidationException::withMessages(['changes' => "Tipo de cambio desconocido: $type"]);
        }
    }

    /**
     * Mezcla el parche sobre el prescrito actual: solo claves editables; ''/null borra la clave.
     * Las demás claves (tempo, duración...) se conservan intactas.
     *
     * @param  array<string,mixed>  $patch
     * @param  array<string,mixed>  $current
     */
    private function cleanPrescribed(array $patch, array $current): array
    {
        $result = $current;
        foreach ($patch as $key => $value) {
            if (!in_array($key, self::EDITABLE_KEYS, true)) {
                continue;
            }
            if ($value === null || (is_string($value) && trim($value) === '')) {
                unset($result[$key]);
            } else {
                $result[$key] = is_scalar($value) ? (string) $value : null;
            }
        }

        return $result;
    }

    /** Notas del ejercicio: texto o null (vacío = sin nota). */
    private function cleanNotes($notes): ?string
    {
        if ($notes === null) {
            return null;
        }
        $notes = trim((string) $notes);

        return $notes === '' ? null : $notes;
    }

    /**
     * Añade a enabled_metrics las métricas opcionales cuyo valor está relleno en `prescribed`
     * (tempo, duración), sin quitar ninguna. Conserva el orden existente.
     *
     * @param  array<int,string>  $metrics
     * @param  array<string,mixed>  $prescribed
     * @return array<int,string>
     */
    private function withMetricsForValues(array $metrics, array $prescribed): array
    {
        $metrics = array_values($metrics);
        foreach (self::METRICS_ENABLED_BY_VALUE as $key) {
            $value = $prescribed[$key] ?? null;
            if ($value !== null && trim((string) $value) !== '' && !in_array($key, $metrics, true)) {
                $metrics[] = $key;
            }
        }

        return $metrics;
    }

    /**
     * Reordena los ejercicios de una sesión: `order` es la lista de ids de fila en el orden deseado.
     * Dentro de CADA bloque, los ejercicios pasan a `sequence` 1..n siguiendo ese orden; los que no
     * aparezcan en la lista conservan su orden relativo y van al final. No mueve ejercicios entre bloques.
     *
     * @param  array<int,mixed>  $order
     */
    private function reorder(WorkoutTemplate $template, int $assignmentId, array $order, ?array $map): void
    {
        $position = [];
        foreach (array_values($order) as $i => $id) {
            $position[$map[(int) $id] ?? (int) $id] = $i;
        }

        $blocks = WorkoutTemplateBlock::where('workout_template_id', $template->id)->with('exercises')->get();
        $known = $blocks->flatMap(fn ($b) => $b->exercises->pluck('id'))->flip();
        foreach (array_keys($position) as $id) {
            if (!$known->has($id)) {
                throw ValidationException::withMessages(['changes' => 'Un ejercicio a reordenar no pertenece a la sesión '.$assignmentId.'.']);
            }
        }

        foreach ($blocks as $block) {
            $sorted = $block->exercises->sortBy(fn ($e) => [$position[$e->id] ?? PHP_INT_MAX, $e->sequence, $e->id])->values();
            foreach ($sorted as $i => $exercise) {
                if ((int) $exercise->sequence !== $i + 1) {
                    $exercise->update(['sequence' => $i + 1]);
                }
            }
        }
    }

    /** enabled_metrics debe incluir "rir" o "rpe" (sensación subjetiva obligatoria). Null = sin cambio. */
    private function cleanMetrics($metrics): ?array
    {
        if ($metrics === null) {
            return null;
        }
        $metrics = array_values(array_unique(array_filter(array_map('strval', (array) $metrics))));
        if (!in_array('rir', $metrics, true) && !in_array('rpe', $metrics, true)) {
            throw ValidationException::withMessages(['changes' => 'enabled_metrics debe incluir "rir" o "rpe" (sensación subjetiva obligatoria).']);
        }

        return $metrics;
    }
}
