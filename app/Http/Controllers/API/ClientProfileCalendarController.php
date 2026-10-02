<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrainingProgram;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Models\WorkoutSessionReview;
use App\Services\CalendarDateMapper;
use App\Services\EmptySessionAlertService;
use App\Services\TemplateIsolationGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ClientProfileCalendarController extends Controller
{
    /** Fecha ancla fija para todos los calendarios personales — estable, no depende de cuándo se creó. */
    const PERSONAL_ANCHOR_DATE = '2020-01-06'; // un lunes

    /**
     * Encuentra (o crea la primera vez) el calendario personal de este
     * cliente. num_weeks muy alto = efectivamente "sin límite" para uso
     * normal (unos 19 años).
     */
    // AÑADIDO (entrenamientos personalizados del cliente, 2026-09-24):
    // public static para que ClientCustomWorkoutController (el propio
    // cliente creando su sesión desde la app) reutilice exactamente el
    // mismo calendario personal en vez de crear uno paralelo. $coach_id
    // solo se usa si hay que crearlo: desde el panel es el coach
    // autenticado (comportamiento de siempre); desde la app, el coach del
    // cliente (o el propio cliente si no tiene coach).
    public static function getOrCreatePersonalProgram(int $client_id, ?int $coach_id = null): TrainingProgram
    {
        $program = TrainingProgram::where('personal_client_id', $client_id)
            ->where('is_personal', true)
            ->first();

        // num_weeks=1000 -> fecha_fin cae ~19 años después del ancla: el
        // calendario personal, por diseño, nunca dispara el cierre
        // automático de mesociclo (check:mesocycle-closures) — es correcto,
        // no es un mesociclo real con fin.
        $anchorDate = Carbon::parse(self::PERSONAL_ANCHOR_DATE);
        $personalFechaFin = ProgramClientAssignment::computeFechaFin($anchorDate, 1000);

        if ($program) {
            // Asegura que el programa personal tenga su fila en
            // program_client_assignments (programas creados antes de esa
            // lógica no la tienen), o getMergedMonth nunca lo recorrería.
            ProgramClientAssignment::firstOrCreate(
                ['training_program_id' => $program->id, 'client_id' => $client_id],
                ['start_date' => self::PERSONAL_ANCHOR_DATE, 'fecha_fin' => $personalFechaFin->toDateString(), 'activo' => true]
            );

            return $program;
        }

        $program = TrainingProgram::create([
            'title'              => 'Calendario personal',
            'is_personal'        => true,
            'personal_client_id' => $client_id,
            'coach_id'           => $coach_id ?? auth()->id(),
            'num_weeks'          => 1000,
            'fecha_inicio'       => self::PERSONAL_ANCHOR_DATE,
            'activo'             => true,
        ]);

        ProgramClientAssignment::create([
            'training_program_id' => $program->id,
            'client_id'            => $client_id,
            'start_date'           => self::PERSONAL_ANCHOR_DATE,
            'fecha_fin'            => $personalFechaFin->toDateString(),
            'activo'               => true,
        ]);

        return $program;
    }

    /**
     * El calendario del mes, combinando TODOS los programas activos
     * asignados a este cliente (el personal + cualquier plantilla
     * importada), fusionados en una sola vista por fecha.
     */
    public function getMergedMonth(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'year'      => 'required|integer',
            'month'     => 'required|integer|min:1|max:12',
        ]);

        $personal_program = $this->getOrCreatePersonalProgram($request->client_id);

        $client_assignments = ProgramClientAssignment::where('client_id', $request->client_id)
            ->where('activo', true)
            ->with('trainingProgram')
            ->get();

        $mapper = new CalendarDateMapper();
        $grid_dates = $mapper->getMonthGridDates((int) $request->year, (int) $request->month);
        $personal_anchor = Carbon::parse(self::PERSONAL_ANCHOR_DATE);

        // AÑADIDO: week_number calculado sobre el calendario PERSONAL (fecha
        // ancla fija) — sirve para etiquetar la fila aunque el día combine
        // entrenamientos de varios programas distintos.
        $days_map = collect($grid_dates)->keyBy(fn ($d) => $d->toDateString())
            ->map(function ($d) use ($mapper, $personal_anchor, $request) {
                $personal_wd = $mapper->toWeekAndDay($personal_anchor, $d);
                return [
                    'date'                 => $d->toDateString(),
                    'in_month'             => $d->month == $request->month,
                    'personal_week_number' => $personal_wd['week_number'],
                    'workouts'             => collect(),
                ];
            });

        foreach ($client_assignments as $client_assignment) {
            $program = $client_assignment->trainingProgram;
            if (!$program) continue;

            $start_date = Carbon::parse($client_assignment->start_date);

            $assignments = ProgramDayAssignment::where('training_program_id', $program->id)
                ->with('workoutTemplate')
                ->get()
                ->groupBy(fn ($a) => $a->week_number.'-'.$a->day_of_week);

            // Batch-load exercise counts for ALL workout templates in this program
            $allTemplateIds = $assignments->flatten()->pluck('workout_template_id')->filter()->unique()->values()->all();
            $exerciseCounts = WorkoutTemplateExercise::whereIn('workout_template_blocks.workout_template_id', $allTemplateIds)
                ->join('workout_template_blocks', 'workout_template_exercises.workout_template_block_id', '=', 'workout_template_blocks.id')
                ->selectRaw('workout_template_blocks.workout_template_id as wt_id, COUNT(*) as cnt')
                ->groupBy('workout_template_blocks.workout_template_id')
                ->pluck('cnt', 'wt_id');

            // Batch-load media for all templates
            $allMedia = \App\Models\WorkoutTemplate::whereIn('id', $allTemplateIds)
                ->get()
                ->mapWithKeys(fn ($wt) => [$wt->id => $wt->getFirstMedia('image')?->getUrl()]);

            foreach ($grid_dates as $date) {
                $wd = $mapper->toWeekAndDay($start_date, $date);
                if ($wd['week_number'] < 1 || $wd['week_number'] > $program->num_weeks) continue;

                $day_assignments = $assignments->get($wd['week_number'].'-'.$wd['day_of_week'], collect());

                foreach ($day_assignments as $a) {
                    if (!$a->workout_template_id) continue;

                    $wt = $a->workoutTemplate;

                    $days_map[$date->toDateString()]['workouts']->push([
                        'assignment_id'         => $a->id,
                        'id'                    => $a->workout_template_id,
                        'title'                 => $wt?->title,
                        'program_title'         => $program->is_personal ? null : $program->title,
                        'is_personal'           => $program->is_personal,
                        'training_program_id'   => $program->is_personal ? null : $program->id,
                        'thumbnail'             => $allMedia->get($a->workout_template_id),
                        'exercise_count'        => $exerciseCounts->get($a->workout_template_id, 0),
                        // AÑADIDO (2026-09-24): lo creó el propio cliente desde
                        // la app (ClientCustomWorkoutController), no el coach.
                        'is_client_created'     => $wt?->created_by_client_id !== null,
                    ]);
                }
            }
        }

        return json_custom_response([
            'data' => [
                'days'                     => $days_map->values(),
                'personal_training_program_id' => $personal_program->id, // AÑADIDO
            ],
        ]);
    }

    /** Asignación directa — siempre va al calendario PERSONAL del cliente. */
    public function assignDirect(Request $request)
    {
        $request->validate([
            'client_id'           => 'required|exists:users,id',
            'date'                => 'required|date',
            'workout_template_id' => 'required|exists:workout_templates,id',
        ]);

        $program = $this->getOrCreatePersonalProgram($request->client_id);
        $mapper = new CalendarDateMapper();
        $wd = $mapper->toWeekAndDay(Carbon::parse(self::PERSONAL_ANCHOR_DATE), Carbon::parse($request->date));

        // SEGURIDAD (auditoría 2026-09-18): antes se enlazaba el
        // workout_template_id del catálogo tal cual -- si el mismo workout
        // se asignaba directo a otro cliente (u otra fecha de este mismo
        // programa personal), ambas asignaciones acababan compartiendo la
        // misma plantilla, y personalizar la sesión de uno vía
        // SessionDetailController::addExercise/addBlock/removeExercise
        // mutaba la del otro. Clonar aquí garantiza que esta asignación
        // nunca comparte fila con ninguna otra.
        $template = WorkoutTemplate::findOrFail($request->workout_template_id);
        $clone = $template->cloneStructure();

        $assignment = ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'          => $wd['week_number'],
            'day_of_week'          => $wd['day_of_week'],
            'workout_template_id'  => $clone->id,
            'scheduled_date'       => $request->date,
        ]);

        return json_custom_response(['data' => $assignment]);
    }

    /**
     * "Importar programa completo" — asigna una plantilla de la
     * biblioteca a este cliente con la fecha de inicio elegida. A
     * partir de ahí, sus días se sincronizan solos en este calendario
     * combinado (es justo `ProgramClientAssignment`, ya existente).
     */
    public function importProgram(Request $request)
    {
        $request->validate([
            'client_id'            => 'required|exists:users,id',
            'training_program_id'  => 'required|exists:training_programs,id',
            'start_date'           => 'required|date',
        ]);

        $program = TrainingProgram::findOrFail($request->training_program_id);
        $startDate = Carbon::parse($request->start_date);
        $fechaFin = ProgramClientAssignment::computeFechaFin($startDate, $program->num_weeks);

        // BUG REAL (2026-08-13, reportado por cliente): esto antes era un
        // create() sin comprobar duplicados. Si el coach reimportaba el
        // mismo programa a un cliente que ya lo tenía activo (doble clic,
        // "reiniciar programa" pulsado dos veces, etc.) se creaban DOS filas
        // program_client_assignments activas para el mismo
        // client_id+training_program_id, cada una con su propio start_date.
        // getMyMonth/getMergedMonth recorren TODAS las asignaciones activas
        // y proyectan cada ProgramDayAssignment (compartido por el programa)
        // sobre las fechas de CADA asignación — el mismo assignment_id
        // terminaba apareciendo en dos fechas reales distintas del
        // calendario. Como la finalización de sesión se guarda por
        // program_day_assignment_id sin fecha, completar la ocurrencia de
        // una fecha marcaba también como "hecha" la ocurrencia de la otra
        // fecha (verificado con datos reales: cliente 8, programa 21,
        // assignment_id 1040 proyectado a la vez en 2026-08-03 y
        // 2026-08-31). Ahora: si ya existe una asignación activa para este
        // client_id+training_program_id, se actualiza en vez de duplicarse
        // (mismo criterio que reiniciar/mover la fecha de inicio).
        // BUG REAL (reportado 2026-09-20): $assignment apuntaba directamente
        // a $request->training_program_id -- el programa de la BIBLIOTECA.
        // program_day_assignments (y sus workout_template_id) es una tabla
        // compartida por training_program_id, no por cliente: borrar un
        // entrenamiento del calendario de este cliente (removeAssignment()
        // más abajo, un simple ProgramDayAssignment::delete()) borraba esa
        // fila para CUALQUIER otro cliente con el mismo programa asignado,
        // incluida la propia plantilla de la biblioteca. Ver
        // TrainingProgram::cloneForClient() para el arreglo completo (mismo
        // patrón ya usado en assignDirect() para un entrenamiento suelto).
        //
        // La comprobación de "ya asignado" mira también source_id (el
        // programa original del que se clonó), no solo el id directo --
        // una asignación ya clonada nunca vuelve a tener
        // training_program_id igual al de la biblioteca, pero una
        // asignación previa a este fix sí. Reasignar solo mueve fechas,
        // nunca reclona (no debe borrar personalizaciones/historial ya
        // existentes sobre la copia de este cliente).
        $assignment = ProgramClientAssignment::where('client_id', $request->client_id)
            ->where('activo', true)
            ->whereHas('trainingProgram', function ($q) use ($request) {
                $q->where('id', $request->training_program_id)
                  ->orWhere('source_id', $request->training_program_id);
            })
            ->first();

        if ($assignment) {
            $assignment->update([
                'start_date' => $request->start_date,
                'fecha_fin'  => $fechaFin->toDateString(),
            ]);
        } else {
            $clientCopy = $program->cloneForClient((int) $request->client_id);

            $assignment = ProgramClientAssignment::create([
                'training_program_id' => $clientCopy->id,
                'client_id'            => $request->client_id,
                'start_date'           => $request->start_date,
                'fecha_fin'            => $fechaFin->toDateString(),
                'activo'               => true,
            ]);
        }

        return json_custom_response(['data' => $assignment]);
    }

    public function removeAssignment(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:program_day_assignments,id',
            'client_id'     => 'nullable|exists:users,id',
        ]);

        // AISLAMIENTO: quitar un día de un programa de la BIBLIOTECA (o de otro
        // cliente) lo quitaría para todos los que lo comparten -- solo se
        // permite sobre la copia propia del cliente o su calendario personal.
        $assignment = ProgramDayAssignment::findOrFail($request->assignment_id);
        if ($blocked = TemplateIsolationGuard::assignmentOutsideClient($assignment, $request->filled('client_id') ? (int) $request->client_id : null)) {
            return $blocked;
        }

        $assignment->delete();
        return json_message_response('Entrenamiento quitado.');
    }

    /**
     * Vaciado en bloque del calendario del cliente — tres alcances, un solo
     * endpoint (panel: selección múltiple de sesiones, "Vaciar semana" y
     * "Vaciar calendario"):
     *   - selection: los `assignment_ids` marcados a mano.
     *   - week:      todo lo que cae entre `from` y `to` (fechas reales).
     *   - all:       el calendario completo del cliente, sin límite de mes.
     *
     * Dos reglas que NO se negocian aquí:
     *   1. Solo se quita lo NO realizado. Una sesión con reseña finalizada
     *      (WorkoutSessionReview::completed_at) se respeta, igual que
     *      cualquier otra sesión del mismo día que el cliente ya cerró —
     *      mismo criterio con el que el panel la pinta en verde (ver
     *      SessionDetailController::listCompletedSessions).
     *   2. Aislamiento: solo se tocan días de la copia propia del cliente o
     *      de su calendario personal. Un día de un programa de la biblioteca
     *      compartido con otros clientes se cuenta como bloqueado y se deja
     *      intacto (TemplateIsolationGuard), nunca se borra "de paso".
     * El borrado es soft-delete (ProgramDayAssignment usa SoftDeletes), así
     * que un vaciado por error es recuperable en base de datos.
     */
    public function bulkRemoveAssignments(Request $request)
    {
        $request->validate([
            'client_id'        => 'required|exists:users,id',
            'scope'            => 'required|in:selection,week,all',
            'assignment_ids'   => 'required_if:scope,selection|array|min:1',
            'assignment_ids.*' => 'integer',
            'from'             => 'required_if:scope,week|nullable|date',
            'to'               => 'required_if:scope,week|nullable|date|after_or_equal:from',
        ]);

        $client_id = (int) $request->client_id;
        $calendar = $this->clientCalendarAssignments($client_id);
        $candidates = $calendar;
        $requested_missing = 0;

        // Cuántas sesiones tiene cada fecha en TODO el calendario (no solo entre
        // las candidatas): decide si una reseña sin enlace a la asignación puede
        // atribuirse a un día concreto. Ver más abajo.
        $per_date = array_count_values(array_column($calendar, 'date'));

        if ($request->scope === 'selection') {
            $wanted = array_unique(array_map('intval', $request->input('assignment_ids', [])));
            $candidates = array_intersect_key($candidates, array_flip($wanted));
            $requested_missing = count($wanted) - count($candidates);
        } elseif ($request->scope === 'week') {
            $from = Carbon::parse($request->from)->toDateString();
            $to   = Carbon::parse($request->to)->toDateString();
            $candidates = array_filter($candidates, fn ($c) => $c['date'] >= $from && $c['date'] <= $to);
        }

        $reviews = WorkoutSessionReview::where('user_id', $client_id)
            ->whereNotNull('completed_at')
            ->get(['program_day_assignment_id', 'completed_at']);

        $completed_ids = $reviews->pluck('program_day_assignment_id')
            ->filter()->map(fn ($id) => (int) $id)->unique()->flip();
        $completed_dates = $reviews->pluck('completed_at')
            ->filter()->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->flip();

        $deleted = 0;
        $skipped_completed = 0;
        $skipped_blocked = 0;

        // El guard solo depende del programa al que pertenece el día, así que se
        // resuelve una vez por programa y no una por sesión (vaciar un
        // calendario entero son decenas de días).
        $blocked_programs = [];

        DB::transaction(function () use ($candidates, $completed_ids, $completed_dates, $per_date, $client_id, &$blocked_programs, &$deleted, &$skipped_completed, &$skipped_blocked) {
            foreach ($candidates as $assignment_id => $candidate) {
                // El día cerrado sin saber qué sesión era solo cuenta si ese día
                // tiene UNA sola sesión; con varias no se puede atribuir a
                // ninguna y las demás seguirían pendientes sin poder quitarlas.
                $date_counts_as_done = $completed_dates->has($candidate['date'])
                    && ($per_date[$candidate['date']] ?? 0) === 1;

                if ($completed_ids->has($assignment_id) || $date_counts_as_done) {
                    $skipped_completed++;
                    continue;
                }

                $program_id = (int) $candidate['assignment']->training_program_id;
                if (!array_key_exists($program_id, $blocked_programs)) {
                    $blocked_programs[$program_id] = TemplateIsolationGuard::assignmentOutsideClient($candidate['assignment'], $client_id) !== null;
                }
                if ($blocked_programs[$program_id]) {
                    $skipped_blocked++;
                    continue;
                }

                $candidate['assignment']->delete();
                $deleted++;
            }
        });

        $message = $deleted === 0
            ? 'No se ha quitado ningún entrenamiento.'
            : ($deleted === 1 ? '1 entrenamiento quitado del calendario.' : $deleted.' entrenamientos quitados del calendario.');

        if ($skipped_completed > 0) {
            $message .= ' Se han respetado '.$skipped_completed.' ya realizado(s).';
        }
        if ($skipped_blocked > 0) {
            $message .= ' '.$skipped_blocked.' pertenece(n) a un programa compartido con otros clientes y no se han tocado.';
        }
        if ($requested_missing > 0) {
            $message .= ' '.$requested_missing.' ya no estaba(n) en este calendario.';
        }

        return json_custom_response([
            'message' => $message,
            'data'    => [
                'deleted'           => $deleted,
                'skipped_completed' => $skipped_completed,
                'skipped_blocked'   => $skipped_blocked,
                'not_found'         => $requested_missing,
            ],
        ]);
    }

    /**
     * Todas las sesiones del calendario de este cliente con su fecha real,
     * indexadas por assignment_id. Es la misma proyección que
     * getMergedMonth (programas activos del cliente x sus días, vía
     * CalendarDateMapper) pero sin limitarse a la cuadrícula de un mes, que
     * es lo que necesita "Vaciar calendario": el frontend solo tiene cargado
     * el mes visible.
     *
     * Indexar por assignment_id es además el filtro de pertenencia del modo
     * `selection`: un id que no salga de aquí no está en el calendario de
     * este cliente y no se borra.
     *
     * @return array<int,array{assignment:ProgramDayAssignment,date:string}>
     */
    private function clientCalendarAssignments(int $client_id): array
    {
        $this->getOrCreatePersonalProgram($client_id);

        $mapper = new CalendarDateMapper();
        $out = [];

        $client_assignments = ProgramClientAssignment::where('client_id', $client_id)
            ->where('activo', true)
            ->with('trainingProgram')
            ->get();

        foreach ($client_assignments as $client_assignment) {
            $program = $client_assignment->trainingProgram;
            if (!$program) continue;

            $start_date = Carbon::parse($client_assignment->start_date);

            $days = ProgramDayAssignment::where('training_program_id', $program->id)
                ->whereNotNull('workout_template_id')
                ->get();

            foreach ($days as $day) {
                if ($day->week_number < 1 || $day->week_number > $program->num_weeks) continue;

                $out[(int) $day->id] = [
                    'assignment' => $day,
                    'date'       => $mapper->toRealDate($start_date, (int) $day->week_number, (int) $day->day_of_week)->toDateString(),
                ];
            }
        }

        return $out;
    }

    /**
     * Visibilidad admin del feedback post-entrenamiento (workout_feedback_screen.tsx
     * -> finishSession) y de las notas por ejercicio que el cliente escribe
     * durante la sesion (workout_session_screen.tsx) - antes ninguno de los
     * dos era visible desde el panel, solo se guardaban en el backend.
     */
    public function getSessionFeedback(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        $emptySessionAlerts = app(EmptySessionAlertService::class);

        $reviews = WorkoutSessionReview::where('user_id', $request->client_id)
            ->whereNotNull('completed_at')
            ->with(['programDayAssignment.workoutTemplate:id,title', 'workoutTemplate:id,title'])
            ->orderByDesc('completed_at')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id'                => $r->id,
                'date'              => optional($r->completed_at)->toDateTimeString(),
                'workout_title'     => optional($r->programDayAssignment?->workoutTemplate)->title
                                        ?? optional($r->workoutTemplate)->title,
                'duration_seconds'  => $r->duration_seconds,
                'volume_kg'         => $r->volume_kg,
                'calories_burned'   => $r->calories_burned,
                'difficulty_rating' => $r->difficulty_rating,
                'comment'           => $r->comment,
                // AÑADIDO (2026-09-27): `completed_at` no null no significa que se
                // registrara ninguna serie real -- caso Ayoub (EmptySessionAlertService),
                // sesiones finalizadas con volumen 0 y cero filas en
                // client_exercise_logs, pintadas en verde como "hechas". Cualquier
                // consumidor de este endpoint que decida "el cliente ya entrenó" a
                // partir de esta lista (ej. Agente de Onboarding, primera sesión real)
                // debe mirar este campo, no solo `completed_at`. Entrenamientos sueltos
                // sin program_day_assignment_id no tienen plan del coach que comprobar
                // -- mismo criterio que EmptySessionAlertService::evaluate(), se dan
                // por válidos sin comprobar.
                'has_logged_sets'   => $r->program_day_assignment_id
                    ? $emptySessionAlerts->hasLoggedSets((int) $r->user_id, (int) $r->program_day_assignment_id)
                    : true,
            ]);

        // latestSnapshots: la misma nota viaja en cada fila acumulada del
        // ejercicio -- sin esto salía repetida una vez por serie marcada.
        $notes = \App\Models\ClientExerciseLog::where('client_id', $request->client_id)
            ->latestSnapshots((int) $request->client_id)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->with('exercise:id,title')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($log) => [
                'id'            => $log->id,
                'date'          => optional($log->performed_date)->toDateString(),
                'exercise_title' => optional($log->exercise)->title ?? 'Ejercicio',
                'notes'         => $log->notes,
            ]);

        return json_custom_response([
            'data' => [
                'reviews' => $reviews,
                'exercise_notes' => $notes,
            ],
        ]);
    }

    /**
     * Visibilidad admin del chequeo diario de preparación (sueño/agujetas/
     * energía/estrés, ReadinessController::store()) que el cliente rellena
     * antes de Workout Preview - se guarda desde siempre, pero no existía
     * ningún endpoint admin para consultarlo.
     */
    public function getReadinessChecks(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        $checks = \App\Models\DailyReadinessCheck::where('user_id', $request->client_id)
            ->orderByDesc('date')
            ->limit(60)
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'date'           => optional($c->date)->toDateString(),
                'sleep_quality'  => $c->sleep_quality,
                'soreness_level' => $c->soreness_level,
                'energy_level'   => $c->energy_level,
                'stress_level'   => $c->stress_level,
            ]);

        return json_custom_response(['data' => $checks]);
    }

    // Reusa el mismo cálculo que v1/my-workout-adherence (ClientCalendarController::
    // computeAdherence) para que el coach vea exactamente la misma racha/ratio
    // que ve el cliente en la app, sin duplicar la lógica.
    public function getAdherence(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);
        $days = min((int) $request->input('days', 30), 90);

        return json_custom_response(['data' => ClientCalendarController::computeAdherence((int) $request->client_id, $days)]);
    }
}
