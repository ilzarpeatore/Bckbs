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

    /** El coach deja feedback sobre una submission concreta. */
    public function leaveFeedback(Request $request)
    {
        $submission = FormSubmission::find($request->submission_id);

        if ($submission == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Submission']));
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
