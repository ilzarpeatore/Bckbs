<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormQuestion;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\CommonNotification;
use Illuminate\Http\Request;
use App\Support\FuzzySearch;

class FormController extends Controller
{
    public function getList(Request $request)
    {
        $query = Form::withCount('questions')->orderBy('id', 'desc');

        if ($request->kind == 'questionnaire') {
            $query->questionnaires();
        } elseif ($request->kind == 'checkin') {
            $query->checkIns();
        }

        if ($request->filled('search')) {
            FuzzySearch::apply($query, ['title'], $request->search);
        }

        $perPage = $request->get('per_page', 50);
        $items = $query->paginate($perPage);

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ]);
    }

    public function getAssignedList(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'kind'      => 'nullable|in:questionnaire,checkin',
        ]);

        $query = FormAssignment::with(['form.questions', 'submissions' => function ($q) {
            $q->orderByDesc('submitted_at')->limit(1);
        }])
            ->where('client_id', $request->client_id)
            ->where('active', true);

        $query->whereHas('form', function ($q) use ($request) {
            if ($request->kind == 'questionnaire') {
                $q->questionnaires();
            } elseif ($request->kind == 'checkin') {
                $q->checkIns();
            }
        });

        $items = $query->orderBy('created_at', 'desc')->get();

        $items->each(function ($assignment) {
            $latest = $assignment->submissions->first();
            $assignment->submitted = !is_null($latest);
            $assignment->submitted_at = $latest?->submitted_at;
            $assignment->latest_submission_id = $latest?->id;
        });

        return json_custom_response(['data' => $items]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id'          => 'sometimes|exists:forms,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'recurrence'  => 'nullable|string|in:daily,weekly,monthly',
        ]);

        $data = [
            'coach_id'    => auth('sanctum')->id(),
            'title'       => $request->title,
            'description' => $request->description,
            'recurrence'  => $request->recurrence,
        ];

        if ($request->filled('id')) {
            $form = Form::findOrFail($request->id);
            $form->update($data);
        } else {
            $form = Form::create($data);
        }

        $form->loadCount('questions');

        return json_custom_response(['data' => $form, 'message' => 'Form saved.']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:forms,id']);

        Form::findOrFail($request->id)->delete();

        return json_message_response('Form deleted.');
    }

    public function getDetail(Request $request)
    {
        $request->validate(['id' => 'required|exists:forms,id']);

        $form = Form::with(['questions.metric:id,key,label,unit'])->findOrFail($request->id);

        return json_custom_response(['data' => $form]);
    }

    public function storeQuestion(Request $request)
    {
        $request->validate([
            'id'             => 'sometimes|exists:form_questions,id',
            'form_id'        => 'required|exists:forms,id',
            'question_text'  => 'required|string',
            'type'           => 'required|in:text,textarea,number,scale,yes_no,date,multiple_choice,media,star_rating,signature,progress_photos,metric',
            'options'        => 'nullable|array',
            'options.*'      => 'nullable|string',
            'max_files'      => 'nullable|integer|min:1|max:10',
            'metric_id'      => 'nullable|exists:metrics_catalog,id',
            'sync_type'      => 'nullable|in:progress_photos,metric',
            'allow_multiple' => 'sometimes|boolean',
            'placeholder'    => 'nullable|string',
            'scale_max'      => 'sometimes|integer|min:1|max:100',
            'star_max'       => 'sometimes|integer|min:1|max:10',
            'order'          => 'sometimes|integer',
            'is_required'    => 'sometimes|boolean',
        ]);

        $options = $request->options;
        if (is_array($options)) {
            $options = array_values(array_filter($options, fn ($o) => $o !== null && trim((string)$o) !== ''));
        }

        $data = [
            'form_id'        => $request->form_id,
            'question_text'  => $request->question_text,
            'type'           => $request->type,
            'options'        => $options,
            'max_files'      => $request->max_files,
            'metric_id'      => $request->metric_id,
            'sync_type'      => $request->sync_type,
            'allow_multiple' => $request->allow_multiple ?? false,
            'placeholder'    => $request->placeholder,
            'scale_max'      => $request->scale_max ?? 10,
            'star_max'       => $request->star_max ?? 5,
            'order'          => $request->order ?? 0,
            'is_required'    => $request->is_required ?? false,
        ];

        if ($request->filled('id')) {
            $question = FormQuestion::findOrFail($request->id);
            $question->update($data);
        } else {
            $question = FormQuestion::create($data);
        }

        $question->load('metric:id,key,label,unit');

        return json_custom_response(['data' => $question, 'message' => 'Question saved.']);
    }

    public function deleteQuestion(Request $request)
    {
        $request->validate(['id' => 'required|exists:form_questions,id']);

        FormQuestion::findOrFail($request->id)->delete();

        return json_message_response('Question deleted.');
    }

    public function getSubmissionList(Request $request)
    {
        $request->validate([
            'form_id'   => 'sometimes|exists:forms,id',
            'client_id' => 'sometimes|exists:users,id',
        ]);

        $query = FormSubmission::with(['answers.question', 'formAssignment.form', 'formAssignment.client'])
            ->orderBy('submitted_at', 'desc');

        if ($request->filled('form_id')) {
            $query->whereHas('formAssignment', fn ($q) => $q->where('form_id', $request->form_id));
        }

        if ($request->filled('client_id')) {
            $query->whereHas('formAssignment', fn ($q) => $q->where('client_id', $request->client_id));
        }

        $perPage = $request->get('per_page', 50);
        $items = $query->paginate($perPage);

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ]);
    }

    public function leaveFeedback(Request $request)
    {
        $request->validate([
            'submission_id' => 'required|exists:form_submissions,id',
            'coach_feedback' => 'required|string',
        ]);

        $submission = FormSubmission::with('formAssignment.client')->findOrFail($request->submission_id);
        $client = $submission->formAssignment?->client;

        // SEGURIDAD (revision 2026-09-13): admin.api solo exige el rol Spatie
        // 'admin', que en este sistema NO es exclusivo de admins reales --
        // tanto SubAdminController::store() (user_type 'sub_admin', el alta
        // real de staff desde el panel) como el seeder de demo (user_type
        // 'coach') lo asignan por igual, y hoy nada mas en el backend
        // distingue "admin/sub_admin con acceso total" de "coach restringido
        // a sus propios clientes" -- cualquier coach podia dejar feedback en
        // el check-in de un cliente ajeno (de OTRO coach), no solo en los
        // suyos. Se deja pasar sin restriccion a 'admin'/'sub_admin' (mismo
        // acceso que ya tienen hoy en el resto de rutas admin.api, cero
        // regresion) y solo se exige ownership cuando user_type es 'coach'.
        $actor = $request->user();
        $isStaffAdmin = in_array($actor->user_type, ['admin', 'sub_admin'], true);
        $isAssignedCoach = $client && (int) $client->coach_id === (int) $actor->id;

        if (!$isStaffAdmin && !$isAssignedCoach) {
            return json_message_response(__('message.permission_denied_for_account'), 403);
        }

        $submission->update(['coach_feedback' => $request->coach_feedback]);

        if ($client) {
            $client->notify(new CommonNotification('coach_feedback', [
                'id'      => $submission->id,
                'type'    => 'coach_feedback',
                'subject' => 'Feedback de tu coach',
                'message' => 'Tu coach ha dejado feedback en uno de tus check-ins.',
            ]));
        }

        return json_message_response('Feedback saved.');
    }

    /**
     * Sin `scheduled_dates`: comportamiento previo, una asignación recurrente
     * gobernada por Form::recurrence (idempotente por form+cliente). Con
     * `scheduled_dates`: crea una asignación de una sola vez por cada fecha
     * concreta pedida (independiente de la recurrencia del formulario) -- así
     * el coach puede fijar un check-in a un día exacto del calendario del
     * cliente, ademas de (o en vez de) la recurrencia normal.
     */
    public function assign(Request $request)
    {
        $request->validate([
            'form_id'          => 'required|exists:forms,id',
            'client_id'        => 'required|exists:users,id',
            'scheduled_dates'  => 'nullable|array',
            'scheduled_dates.*' => 'date_format:Y-m-d',
        ]);

        $dates = $request->scheduled_dates ?: [null];
        $createdAny = false;

        foreach ($dates as $date) {
            $assignment = FormAssignment::firstOrCreate(
                ['form_id' => $request->form_id, 'client_id' => $request->client_id, 'scheduled_date' => $date],
                ['active' => true]
            );
            $createdAny = $createdAny || $assignment->wasRecentlyCreated;
        }

        if ($createdAny) {
            $client = User::find($request->client_id);
            $form = Form::find($request->form_id);
            if ($client && $form) {
                $client->notify(new CommonNotification('new_checkin', [
                    'id'      => $form->id,
                    'type'    => 'new_checkin',
                    'subject' => 'Nuevo check-in asignado',
                    'message' => "Tu coach te ha asignado \"{$form->title}\".",
                ]));
            }
        }

        return json_message_response('Form assigned to client.');
    }
}
