<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Metric;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\WorkoutSessionReview;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use App\Services\CalendarDateMapper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Entrenamientos personalizados creados por el PROPIO cliente desde la app
 * (pantalla "Crear entrenamiento personalizado", pedido 2026-09-24).
 *
 * No hay un modelo paralelo: cada sesión es un workout_template normal
 * (bloques = secciones, ejercicios con su prescrito y métricas) colgado de
 * un program_day_assignment en el calendario personal del cliente -- el
 * mismo que usa el coach con ClientProfileCalendarController::assignDirect().
 * Así la sesión en vivo, logSets()/finishSession(), el feedback, el
 * historial, las estadísticas y el calendario del coach funcionan sin
 * ningún cambio. created_by_client_id marca que lo creó el cliente.
 */
class ClientCustomWorkoutController extends Controller
{
    /** Tope de repeticiones semanales ("repetir todos los lunes") por creación. */
    const MAX_REPEAT_WEEKS = 52;

    /** Tope de ejercicios por entrenamiento (todas las secciones juntas). */
    const MAX_EXERCISES = 60;

    /**
     * POST v1/my-custom-workouts
     *
     * {
     *   "title": "Pierna en casa",
     *   "date": "2026-09-28",
     *   "repeat_weeks": 8,            // opcional, 1 = sin repetir
     *   "blocks": [
     *     { "title": "Calentamiento", "exercises": [
     *       { "exercise_id": 12, "prescribed": {"series": 3, "reps": "10", "carga": "20", "descanso": "90", "rir": "2"},
     *         "enabled_metrics": ["reps", "carga", "descanso", "rir"], "notes": null }
     *     ]}
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $today = Carbon::today();
        // El servidor va en UTC y el cliente en su hora local: el lunes
        // local puede ser todavía domingo en UTC (o al revés). Se calcula la
        // semana en curso desde AYER para no rechazar el lunes local de un
        // cliente que va por delante/detrás de UTC; sigue sin permitir
        // semanas pasadas reales.
        $minDate = $today->copy()->subDay()->startOfWeek(Carbon::MONDAY)->toDateString();

        $request->validate([
            // Idempotencia: la app manda un id por pantalla de creación. Si
            // la petición se repite (timeout de red y el usuario vuelve a
            // pulsar Guardar) se devuelve lo ya creado en vez de duplicarlo.
            'client_request_id'                 => ['nullable', 'string', 'max:36', 'regex:/^[A-Za-z0-9_-]+$/'],
            'title'                             => 'required|string|max:120',
            'date'                              => 'required|date_format:Y-m-d|after_or_equal:'.$minDate.'|before_or_equal:'.$today->copy()->addYear()->toDateString(),
            'repeat_weeks'                      => 'nullable|integer|min:1|max:'.self::MAX_REPEAT_WEEKS,
            'blocks'                            => 'required|array|min:1|max:20',
            'blocks.*.title'                    => 'nullable|string|max:120',
            'blocks.*.exercises'                => 'present|array|max:40',
            'blocks.*.exercises.*.exercise_id'  => 'required|integer|exists:exercises,id',
            'blocks.*.exercises.*.prescribed'   => 'nullable|array',
            'blocks.*.exercises.*.enabled_metrics'   => 'nullable|array',
            'blocks.*.exercises.*.enabled_metrics.*' => 'string',
            'blocks.*.exercises.*.notes'        => 'nullable|string|max:1000',
        ], [
            'title.required'         => 'El entrenamiento necesita un nombre.',
            'title.max'              => 'El nombre es demasiado largo (máximo 120 caracteres).',
            'date.required'          => 'Elige un día para el entrenamiento.',
            'date.date_format'       => 'La fecha no es válida.',
            'date.after_or_equal'    => 'Solo puedes crear entrenamientos a partir de la semana en curso.',
            'date.before_or_equal'   => 'Solo puedes planificar hasta un año vista.',
            'repeat_weeks.max'       => 'Puedes repetirlo como máximo '.self::MAX_REPEAT_WEEKS.' semanas.',
            'repeat_weeks.integer'   => 'El número de semanas no es válido.',
            'repeat_weeks.min'       => 'El número de semanas no es válido.',
            'blocks.required'        => 'Añade al menos una sección con ejercicios.',
            'blocks.max'             => 'Como máximo puedes tener 20 secciones.',
            'blocks.*.exercises.max' => 'Como máximo puedes tener 40 ejercicios por sección.',
            'blocks.*.exercises.*.exercise_id.exists' => 'Uno de los ejercicios ya no existe. Quítalo y vuelve a añadirlo.',
            // Genéricos (el locale del backend es 'en'): cualquier otro fallo
            // de validación se muestra en castellano en la app.
            'required'               => 'Revisa los datos del entrenamiento: falta información.',
            'present'                => 'Revisa los datos del entrenamiento: falta información.',
            'array'                  => 'Revisa los datos del entrenamiento.',
            'string'                 => 'Revisa los datos del entrenamiento.',
            'integer'                => 'Revisa los datos del entrenamiento.',
            'regex'                  => 'Petición no válida. Cierra y vuelve a abrir el creador.',
            'max'                    => 'Alguno de los textos es demasiado largo.',
            'exists'                 => 'Uno de los ejercicios ya no existe. Quítalo y vuelve a añadirlo.',
        ]);

        $blocks = collect($request->blocks)
            ->map(fn ($b) => ['title' => $b['title'] ?? null, 'exercises' => array_values($b['exercises'] ?? [])])
            ->filter(fn ($b) => count($b['exercises']) > 0)
            ->values();

        if ($blocks->isEmpty()) {
            return json_message_response('Añade al menos un ejercicio al entrenamiento.', 422);
        }
        if ($blocks->sum(fn ($b) => count($b['exercises'])) > self::MAX_EXERCISES) {
            return json_message_response('Como máximo puedes tener '.self::MAX_EXERCISES.' ejercicios en un entrenamiento.', 422);
        }

        $allowedMetrics = Metric::pluck('key')->all();
        // Mismas claves que ya usa el prescrito del coach (ver
        // workout_session_screen.tsx: METRIC_DISPLAY_RANK). 'series' no es
        // una métrica registrable, solo el nº de filas iniciales.
        $allowedPrescribedKeys = array_unique(array_merge($allowedMetrics, ['series']));

        $normalizedBlocks = [];
        foreach ($blocks as $bIdx => $block) {
            $exercises = [];
            foreach ($block['exercises'] as $eIdx => $ex) {
                $metrics = array_values(array_intersect((array) ($ex['enabled_metrics'] ?? []), $allowedMetrics));
                if ($metrics === []) {
                    $metrics = ['reps', 'carga', 'descanso', 'rir'];
                } elseif (!in_array('rir', $metrics, true) && !in_array('rpe', $metrics, true)) {
                    // Misma regla que WorkoutTemplateController::saveExercise():
                    // sensación subjetiva obligatoria (la usa el motor de carga).
                    $metrics[] = 'rir';
                }

                $prescribed = collect((array) ($ex['prescribed'] ?? []))
                    ->only($allowedPrescribedKeys)
                    ->map(fn ($v, $k) => self::normalizePrescribedValue((string) $k, $v))
                    ->filter(fn ($v) => $v !== null)
                    ->all();
                $prescribed['series'] = $prescribed['series'] ?? '3';

                $exercises[] = [
                    'exercise_id'     => (int) $ex['exercise_id'],
                    'sequence'        => $eIdx + 1,
                    'prescribed'      => $prescribed,
                    'enabled_metrics' => $metrics,
                    'notes'           => $ex['notes'] ?? null,
                ];
            }
            $normalizedBlocks[] = [
                'title'     => trim((string) ($block['title'] ?? '')) ?: 'Sección '.($bIdx + 1),
                'order'     => $bIdx + 1,
                'exercises' => $exercises,
            ];
        }

        $user = auth('sanctum')->user();
        $repeatWeeks = (int) ($request->repeat_weeks ?? 1);
        $firstDate = Carbon::parse($request->date);
        $requestId = $request->client_request_id;
        // Siempre hay un id de serie: el que manda la app (idempotencia) o
        // uno nuevo. Con 1 sola semana simplemente agrupa una única fila.
        $seriesUuid = $requestId ?: (string) Str::uuid();
        $replayed = false;

        $created = DB::transaction(function () use ($user, $request, $normalizedBlocks, $repeatWeeks, $firstDate, $seriesUuid, $requestId, &$replayed) {
            // Serializa las creaciones de un mismo cliente (bloqueo de su
            // fila de users): dos peticiones simultáneas con el mismo
            // client_request_id no pueden pasar las dos la comprobación de
            // abajo, ni crear dos calendarios personales a la vez.
            \App\Models\User::whereKey($user->id)->lockForUpdate()->first();

            if ($requestId) {
                $existing = ProgramDayAssignment::whereHas('workoutTemplate', fn ($q) => $q
                        ->where('client_series_uuid', $requestId)
                        ->where('created_by_client_id', $user->id))
                    ->orderBy('scheduled_date')
                    ->get();
                if ($existing->isNotEmpty()) {
                    $replayed = true;
                    return $existing->map(fn ($a) => [
                        'assignment_id'       => $a->id,
                        'workout_template_id' => $a->workout_template_id,
                        'date'                => Carbon::parse($a->scheduled_date)->toDateString(),
                    ])->all();
                }
            }

            $program = ClientProfileCalendarController::getOrCreatePersonalProgram($user->id, $user->coach_id ?: $user->id);
            $mapper = new CalendarDateMapper();
            $anchor = Carbon::parse(ClientProfileCalendarController::PERSONAL_ANCHOR_DATE);

            $out = [];
            for ($i = 0; $i < $repeatWeeks; $i++) {
                $date = $firstDate->copy()->addWeeks($i);
                $template = $this->createTemplate($user, $request->title, $normalizedBlocks, $seriesUuid);
                $wd = $mapper->toWeekAndDay($anchor, $date);

                $assignment = ProgramDayAssignment::create([
                    'training_program_id' => $program->id,
                    'week_number'         => $wd['week_number'],
                    'day_of_week'         => $wd['day_of_week'],
                    'workout_template_id' => $template->id,
                    'scheduled_date'      => $date->toDateString(),
                ]);

                $out[] = [
                    'assignment_id'       => $assignment->id,
                    'workout_template_id' => $template->id,
                    'date'                => $date->toDateString(),
                ];
            }

            return $out;
        });

        $repeatWeeks = count($created);

        return json_custom_response([
            'replayed' => $replayed,
            'message' => $repeatWeeks > 1
                ? "Entrenamiento creado y repetido durante {$repeatWeeks} semanas."
                : 'Entrenamiento creado.',
            'data' => [
                'series_uuid' => $seriesUuid,
                'assignments' => $created,
            ],
        ]);
    }

    /**
     * POST v1/my-custom-workouts-delete
     * { "program_day_assignment_id": 123, "scope": "single" | "following" }
     *
     * Solo lo que creó el propio cliente (nunca lo que asignó su coach) y
     * solo ocurrencias sin completar -- el historial real no se toca.
     * "following" borra esta y todas las repeticiones posteriores de la
     * misma serie semanal.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'program_day_assignment_id' => 'required|integer',
            'scope'                     => 'nullable|in:single,following',
        ]);

        $clientId = auth('sanctum')->id();
        $assignment = ProgramDayAssignment::with('workoutTemplate', 'trainingProgram')->find($request->program_day_assignment_id);

        if (!$assignment
            || !$assignment->trainingProgram
            || !$assignment->trainingProgram->is_personal
            || (int) $assignment->trainingProgram->personal_client_id !== (int) $clientId
            || !$assignment->workoutTemplate
            || (int) $assignment->workoutTemplate->created_by_client_id !== (int) $clientId) {
            return json_message_response('Solo puedes eliminar entrenamientos que hayas creado tú.', 403);
        }

        $targets = collect([$assignment]);
        $seriesUuid = $assignment->workoutTemplate->client_series_uuid;
        if ($request->scope === 'following' && $seriesUuid) {
            $targets = ProgramDayAssignment::with('workoutTemplate')
                ->where('training_program_id', $assignment->training_program_id)
                ->where('scheduled_date', '>=', $assignment->scheduled_date)
                ->whereHas('workoutTemplate', fn ($q) => $q
                    ->where('client_series_uuid', $seriesUuid)
                    ->where('created_by_client_id', $clientId))
                ->get();
        }

        $completedIds = WorkoutSessionReview::where('user_id', $clientId)
            ->whereIn('program_day_assignment_id', $targets->pluck('id'))
            ->pluck('program_day_assignment_id')
            ->flip();

        $deleted = 0;
        DB::transaction(function () use ($targets, $completedIds, &$deleted) {
            foreach ($targets as $t) {
                if ($completedIds->has($t->id)) continue;
                $template = $t->workoutTemplate;
                $t->delete();
                $template?->delete();
                $deleted++;
            }
        });

        if ($deleted === 0) {
            return json_message_response('Este entrenamiento ya está completado y no se puede eliminar.', 422);
        }

        return json_custom_response([
            'message' => $deleted > 1 ? "Se han eliminado {$deleted} entrenamientos." : 'Entrenamiento eliminado.',
            'data'    => ['deleted' => $deleted],
        ]);
    }

    /**
     * GET v1/my-active-programs
     *
     * Programas asignados por el coach (sin el calendario personal) para la
     * sección "Entrenamientos" del Home: nombre, semana en curso y sesiones
     * de esta semana (hechas / totales).
     */
    public function activePrograms(Request $request)
    {
        $clientId = auth('sanctum')->id();
        $today = Carbon::today();
        $mapper = new CalendarDateMapper();

        $assignments = ProgramClientAssignment::where('client_id', $clientId)
            ->where('activo', true)
            ->whereHas('trainingProgram', fn ($q) => $q->where('is_personal', false))
            ->with('trainingProgram')
            ->orderByDesc('start_date')
            ->get();

        $items = $assignments->map(function ($ca) use ($clientId, $today, $mapper) {
            $program = $ca->trainingProgram;
            $start = Carbon::parse($ca->start_date);
            $wd = $mapper->toWeekAndDay($start, $today);
            $started = $today->gte($start->copy()->startOfWeek(Carbon::MONDAY));
            $currentWeek = $started ? max(1, min((int) $program->num_weeks, $wd['week_number'])) : 0;

            $weekIds = $currentWeek > 0
                ? ProgramDayAssignment::where('training_program_id', $program->id)
                    ->where('week_number', $currentWeek)
                    ->whereNotNull('workout_template_id')
                    ->pluck('id')
                : collect();
            $doneThisWeek = $weekIds->isEmpty() ? 0 : WorkoutSessionReview::where('user_id', $clientId)
                ->whereIn('program_day_assignment_id', $weekIds)
                ->distinct('program_day_assignment_id')
                ->count('program_day_assignment_id');

            return [
                'program_client_assignment_id' => $ca->id,
                'training_program_id'          => $program->id,
                'title'                        => $program->title ?: 'Mi programa',
                'start_date'                   => Carbon::parse($ca->start_date)->toDateString(),
                'end_date'                     => $ca->fecha_fin ? Carbon::parse($ca->fecha_fin)->toDateString() : null,
                'num_weeks'                    => (int) $program->num_weeks,
                'current_week'                 => $currentWeek,
                'sessions_this_week'           => $weekIds->count(),
                'completed_this_week'          => $doneThisWeek,
            ];
        })->values();

        return json_custom_response(['data' => $items]);
    }

    /**
     * Deja cada valor prescrito en un formato que la sesión en vivo, el
     * registro de series y el motor de carga saben leer -- aunque llegue de
     * una versión vieja de la app o de un cliente manipulado. null = se
     * descarta la clave.
     */
    public static function normalizePrescribedValue(string $key, $value): ?string
    {
        if (!is_scalar($value)) return null;
        $raw = trim(str_replace(',', '.', (string) $value));
        if ($raw === '') return null;

        $number = function (string $v, float $min, float $max, int $decimals): ?string {
            if (!preg_match('/^\d+(\.\d+)?$/', $v)) return null;
            $n = round(min($max, max($min, (float) $v)), $decimals);
            $out = number_format($n, $decimals, '.', '');
            // Quita ceros decimales sobrantes ("22.50" -> "22.5", "60.00" ->
            // "60") -- solo si hay parte decimal: "120" debe seguir siendo 120.
            return str_contains($out, '.') ? rtrim(rtrim($out, '0'), '.') : $out;
        };

        switch ($key) {
            case 'series':
                return preg_match('/^\d+$/', $raw) ? (string) max(1, min(20, (int) $raw)) : '3';
            case 'reps':
                // "10" o rango "8-10" (el orden se corrige); cualquier otra
                // cosa se queda con su primer número, o se descarta.
                if (preg_match('/^(\d{1,3})\s*-\s*(\d{1,3})$/', $raw, $m)) {
                    [$a, $b] = [max(1, (int) $m[1]), max(1, (int) $m[2])];
                    return $a === $b ? (string) $a : min($a, $b).'-'.max($a, $b);
                }
                return preg_match('/\d{1,3}/', $raw, $m) ? (string) max(1, (int) $m[0]) : null;
            case 'carga':
                return $number($raw, 0, 1000, 2);
            case 'descanso':
            case 'tiempo':
                if (preg_match('/^(\d{1,3}):(\d{1,2})$/', $raw, $m)) {
                    $raw = (string) ((int) $m[1] * 60 + (int) $m[2]);
                }
                return $number($raw, 0, $key === 'descanso' ? 3600 : 36000, 0);
            case 'rir':
                return $number($raw, 0, 10, 1);
            case 'rpe':
                return $number($raw, 1, 10, 1);
            default:
                return mb_substr($raw, 0, 20);
        }
    }

    private function createTemplate($user, string $title, array $blocks, ?string $seriesUuid): WorkoutTemplate
    {
        $template = WorkoutTemplate::create([
            // coach_id es NOT NULL: el coach del cliente (o el propio
            // cliente si no tiene). No aparece en la biblioteca del coach:
            // WorkoutTemplateController::getList() excluye las plantillas
            // con program_day_assignments, e is_public=false la deja fuera
            // del catálogo de clientes.
            'coach_id'             => $user->coach_id ?: $user->id,
            'created_by_client_id' => $user->id,
            'client_series_uuid'   => $seriesUuid,
            'title'                => $title,
            'is_exclusive'         => false,
            'is_public'            => false,
        ]);

        foreach ($blocks as $block) {
            $newBlock = WorkoutTemplateBlock::create([
                'workout_template_id' => $template->id,
                'title'               => $block['title'],
                'order'               => $block['order'],
            ]);

            foreach ($block['exercises'] as $ex) {
                WorkoutTemplateExercise::create([
                    'workout_template_block_id' => $newBlock->id,
                    'exercise_id'               => $ex['exercise_id'],
                    'sequence'                  => $ex['sequence'],
                    'prescribed'                => $ex['prescribed'],
                    'enabled_metrics'           => $ex['enabled_metrics'],
                    'notes'                     => $ex['notes'],
                ]);
            }
        }

        return $template;
    }
}
