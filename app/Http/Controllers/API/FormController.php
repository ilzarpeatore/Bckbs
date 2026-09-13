<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\FormAnswer;
use App\Models\FormQuestion;
use App\Models\UserGraph;
use Illuminate\Support\Facades\DB;

class FormController extends Controller
{
    /**
     * `?kind=questionnaire` o `?kind=checkin` filtra por recurrencia.
     */
    public function getAssignedList(Request $request)
    {
        $user = auth('sanctum')->user();

        $assignments = FormAssignment::with(['form.questions.metric', 'submissions' => function ($q) {
            $q->orderByDesc('submitted_at')->limit(1);
        }])
        ->where('client_id', $user->id)
        ->where('active', true)
        ->whereHas('form', function ($q) use ($request) {
            if ($request->kind == 'questionnaire') {
                $q->questionnaires();
            } elseif ($request->kind == 'checkin') {
                $q->checkIns();
            }
        })
        ->get();

        $assignments->each(function ($assignment) {
            $latest = $assignment->submissions->first();
            $assignment->submitted = !is_null($latest);
            $assignment->submitted_at = $latest?->submitted_at;
            $assignment->latest_submission_id = $latest?->id;

            // "Pendiente": nunca se envió, o (para check-ins recurrentes) el
            // último envío es de un periodo anterior al actual. Un
            // cuestionario (recurrence null) deja de pedirse en cuanto se
            // envía una vez. Una asignación con scheduled_date (fecha fija
            // puesta por el coach, ver getAssignedCalendar) solo se considera
            // pendiente el día exacto para el que se programó -- antes o
            // después de esa fecha no debe aparecer como tarea de "hoy".
            if ($assignment->scheduled_date) {
                $assignment->is_due = $assignment->scheduled_date->isToday() && is_null($latest);
            } elseif (is_null($latest)) {
                $assignment->is_due = true;
            } elseif (is_null($assignment->form->recurrence)) {
                $assignment->is_due = false;
            } else {
                $periodStart = match ($assignment->form->recurrence) {
                    'daily'   => now()->startOfDay(),
                    'weekly'  => now()->startOfWeek(),
                    'monthly' => now()->startOfMonth(),
                    default   => now()->startOfDay(),
                };
                $assignment->is_due = $latest->submitted_at->lt($periodStart);
            }
        });

        return json_custom_response(['data' => $assignments]);
    }

    /**
     * Asignaciones con fecha fija (scheduled_date) del cliente autenticado
     * dentro de un mes/año concreto -- para proyectarlas en las celdas del
     * calendario (my_program_calendar_screen.tsx). Las recurrentes (sin
     * fecha fija) no tienen un día concreto que proyectar y se siguen
     * resolviendo solo para "hoy" vía getAssignedList.
     */
    public function getAssignedCalendar(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2000|max:2100',
        ]);

        $user = auth('sanctum')->user();
        $start = now()->setDate((int) $request->year, (int) $request->month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $assignments = FormAssignment::with('form')
            ->where('client_id', $user->id)
            ->where('active', true)
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $assignments->each(function ($assignment) {
            $latest = $assignment->submissions()->orderByDesc('submitted_at')->first();
            $assignment->submitted = !is_null($latest);
            $assignment->submitted_at = $latest?->submitted_at;
            $assignment->latest_submission_id = $latest?->id;
            $assignment->is_due = is_null($latest);
        });

        return json_custom_response(['data' => $assignments]);
    }

    public function getDetail(Request $request)
    {
        $form = Form::with('questions.metric')->find($request->id);

        if ($form == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Form']));
        }

        return json_custom_response(['data' => $form]);
    }

    /**
     * El cliente envía sus respuestas. Se crea la submission y sus answers.
     * Soporta multipart para subir imágenes de preguntas media/progress_photos.
     * Sincroniza métricas a UserGraph.
     */
    public function submit(Request $request)
    {
        $request->validate([
            'form_assignment_id' => 'required|exists:form_assignments,id',
            'answers'            => 'required|array',
            'answers.*.form_question_id' => 'required|exists:form_questions,id',
            'answers.*.answer_value'     => 'nullable',
        ]);

        $user = auth('sanctum')->user();
        $assignment = FormAssignment::with('form')->findOrFail($request->form_assignment_id);

        if ($assignment->client_id != $user->id) {
            return json_message_response('Unauthorized', 403);
        }

        $questionIds = collect($request->answers)->pluck('form_question_id')->unique()->values()->all();
        $questionsMap = FormQuestion::with('metric')->whereIn('id', $questionIds)->get()->keyBy('id');

        DB::beginTransaction();
        try {
            $submission = FormSubmission::create([
                'form_assignment_id' => $request->form_assignment_id,
                'submitted_at'       => now(),
            ]);

            foreach ($request->answers as $answer) {
                $question = $questionsMap->get($answer['form_question_id']);
                if (!$question) {
                    DB::rollBack();
                    return json_message_response('Question not found: ' . $answer['form_question_id'], 422);
                }
                $value = $answer['answer_value'] ?? null;

                // Normalize array values (e.g. multiple choice) to JSON
                if (is_array($value)) {
                    $value = json_encode($value);
                }

                // Media / Progress Photos: upload files to user media collection
                if (in_array($question->type, ['media', 'progress_photos'])) {
                    $files = [];
                    if ($request->hasFile("media_{$question->id}")) {
                        $uploaded = $request->file("media_{$question->id}");
                        $uploaded = is_array($uploaded) ? $uploaded : [$uploaded];
                        foreach ($uploaded as $file) {
                            $collection = $question->type === 'progress_photos' ? 'progress_photos' : 'form_media';
                            $media = $user->addMedia($file)
                                ->usingName("Form media")
                                ->toMediaCollection($collection);
                            $files[] = $media->getUrl();
                        }
                    }
                    $value = json_encode($files);
                }

                // Metric sync: also save to user_graphs
                if ($question->type === 'metric' && $question->metric_id && $question->metric && is_numeric($value)) {
                    UserGraph::create([
                        'user_id' => $user->id,
                        'value'   => $value,
                        'type'    => $question->metric->key,
                        'unit'    => $question->metric->unit,
                        'date'    => now()->toDateString(),
                    ]);
                }

                FormAnswer::create([
                    'form_submission_id' => $submission->id,
                    'form_question_id'   => $question->id,
                    'answer_value'       => $value,
                ]);
            }

            DB::commit();
            return json_message_response(__('message.save_form', ['form' => 'Check-In']));
        } catch (\Throwable $e) {
            DB::rollBack();
            return json_message_response('Failed to submit: ' . $e->getMessage(), 500);
        }
    }

    /**
     * El coach deja feedback sobre una submission concreta.
     *
     * SEGURIDAD (revision 2026-09-13, IDOR): esta ruta vive en el grupo
     * auth:sanctum generico (no /admin), asi que cualquier usuario
     * autenticado -- no solo coaches -- podia mandar cualquier
     * submission_id y escribir coach_feedback en el check-in de OTRO
     * cliente que ni siquiera es suyo. Se exige que el caller sea el coach
     * del cliente dueño de esa submission (o admin), mismo criterio
     * coach_id/client_id === auth()->id() usado en el resto del proyecto
     * (SessionInterpretationController::exerciseMetrics, etc.).
     */
    public function leaveFeedback(Request $request)
    {
        $submission = FormSubmission::with('formAssignment.client')->find($request->submission_id);

        if ($submission == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Submission']));
        }

        $authUser = $request->user();
        $client = $submission->formAssignment?->client;
        $isCoach = $authUser && $client && (int) $client->coach_id === (int) $authUser->id;
        $isAdmin = $authUser && $authUser->hasRole('admin');

        if (!$isCoach && !$isAdmin) {
            return json_message_response(__('message.permission_denied_for_account'), 403);
        }

        $submission->update(['coach_feedback' => $request->coach_feedback]);

        return json_message_response(__('message.save_form', ['form' => 'Feedback']));
    }

    /** Asignar un formulario existente a un cliente — desde el panel Admin. */
    public function assign(Request $request)
    {
        $request->validate([
            'form_id'   => 'required|exists:forms,id',
            'client_id' => 'required|exists:users,id',
        ]);

        FormAssignment::firstOrCreate(
            ['form_id' => $request->form_id, 'client_id' => $request->client_id],
            ['active' => true]
        );

        return json_message_response(__('message.save_form', ['form' => 'Form Assignment']));
    }
}
