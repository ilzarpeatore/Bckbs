<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Services\AuditLogger;
use App\Services\WelcomeMailService;
use App\Exports\UserReportExport;
use Illuminate\Http\Request;
use App\Support\FuzzySearch;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class UserController extends BaseController
{
    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function getResourceClass(): string
    {
        return UserResource::class;
    }

    public function index(Request $request)
    {
        $query = User::role('user');

        if ($request->filled('search')) {
            FuzzySearch::apply($query, ['first_name', 'last_name', 'email', 'username'], $request->search);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        if ($perPage == -1 || $perPage > 250) {
            $perPage = 250;
        }
        $items = $query->orderBy('id', 'desc')->paginate($perPage);
        $items = UserResource::collection($items);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    public function store(Request $request)
    {
        // Igual que SettingController::updateProfile: se normaliza antes de
        // validar, porque `setPhoneNumberAttribute` guarda sin '+' -- si no,
        // "unique" compara el valor crudo contra lo ya guardado sin '+' y
        // nunca detecta la colisión.
        if ($request->filled('phone_number')) {
            $request->merge(['phone_number' => str_replace('+', '', $request->phone_number)]);
        }

        $request->validate([
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'username'     => 'required|unique:users,username',
            'password'     => 'required|string|min:8',
            'phone_number' => 'nullable|string|max:20|unique:users,phone_number',
            'gender'       => 'nullable|in:male,female,other',
            'is_personal_client' => 'sometimes|boolean',
        ]);

        $data = $request->all();
        $data['password'] = Hash::make($data['password']);
        $data['user_type'] = 'user';
        $data['status'] = $request->get('status', 'active');
        $data['display_name'] = $data['first_name'] . ' ' . $data['last_name'];
        // Decisión de negocio (2026-09-17): igual que en el registro desde
        // la app (API\UserController::register), por defecto 1:1, no free,
        // salvo que el coach lo desmarque explícitamente en el formulario.
        $data['is_personal_client'] = $request->boolean('is_personal_client', true);

        $user = User::create($data);
        $user->assignRole('user');
        WelcomeMailService::sendFor($user);

        $response = [
            'message' => 'User created successfully.',
            'data'    => new UserResource($user),
        ];

        return json_custom_response($response, 201);
    }

    /** Nombre para mostrar de un miembro del personal. */
    private function staffName(User $u): string
    {
        return $u->display_name ?: (trim("{$u->first_name} {$u->last_name}") ?: $u->email);
    }

    /** Coach actual del cliente + personal que se puede asignar (coaches y admins activos). */
    private function coachPayload(User $client): array
    {
        $current = $client->coach_id ? User::find($client->coach_id) : null;

        return [
            'client_id' => $client->id,
            'coach'     => $current ? [
                'id' => $current->id, 'name' => $this->staffName($current), 'user_type' => $current->user_type,
            ] : null,
            'options'   => User::whereIn('user_type', ['coach', 'admin'])
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $this->staffName($u), 'user_type' => $u->user_type])
                ->values(),
        ];
    }

    /**
     * GET admin/users/lookup-by-phone?phone=<numero>
     *
     * Resuelve teléfono -> cliente_id sin la hoja de mapeo manual que hoy
     * usa el Agente de Soporte / Customer Success (gap documentado en
     * docs/TAREAS_PENDIENTES.md, AgenticdesignBS, ítem 2.16/2.20). El
     * número puede llegar en cualquier formato (con '+', espacios, el
     * prefijo "whatsapp:" que manda Twilio) -- aquí se reduce a solo
     * dígitos y se compara contra `phone_number` (guardado sin '+' por
     * `setPhoneNumberAttribute`). Si no hay coincidencia exacta, se prueba
     * también contra los últimos 9 dígitos (número de móvil español sin
     * prefijo de país) porque no todos los clientes existentes tienen el
     * número guardado con el prefijo -- WhatsApp siempre lo manda completo.
     * Nunca elige entre varias coincidencias: si hay más de una, la
     * responsabilidad de desambiguar es humana.
     */
    public function lookupByPhone(Request $request)
    {
        $request->validate(['phone' => 'required|string']);

        $digits = preg_replace('/\D+/', '', $request->phone);
        if ($digits === '') {
            return json_message_response('Número de teléfono inválido.', 422);
        }

        $matches = User::role('user')->where('phone_number', $digits)->get();

        if ($matches->isEmpty() && strlen($digits) > 9) {
            $matches = User::role('user')->where('phone_number', substr($digits, -9))->get();
        }

        if ($matches->isEmpty()) {
            return json_message_response('No se encontró ningún cliente con ese número.', 404);
        }

        if ($matches->count() > 1) {
            return json_custom_response([
                'message' => 'Más de un cliente coincide con ese número -- revisión manual necesaria.',
                'data'    => UserResource::collection($matches),
            ], 409);
        }

        return json_custom_response(['data' => new UserResource($matches->first())]);
    }

    /** GET admin/users/{user}/coach */
    public function coachShow(Request $request, $id)
    {
        $client = User::role('user')->find($id);
        if (!$client) {
            return json_message_response('User not found.', 404);
        }

        return json_custom_response(['data' => $this->coachPayload($client)]);
    }

    /**
     * PUT admin/users/{user}/coach { coach_id: int|null }
     *
     * Asigna (o quita, con null) el entrenador de un cliente. Con coach_id
     * vacío el motor de progresión no aplica ninguna regla y los avisos "al
     * coach del cliente" (dolor, sesiones sin registrar...) se saltan al
     * cliente, así que conviene que todo cliente tenga uno. Solo un admin
     * (user_type=admin) puede reasignar: un coach no debe poder mover clientes
     * de otro coach.
     */
    public function coachUpdate(Request $request, $id)
    {
        if ($request->user()?->user_type !== 'admin') {
            return json_message_response('Solo un administrador puede asignar entrenadores.', 403);
        }

        $client = User::role('user')->find($id);
        if (!$client) {
            return json_message_response('User not found.', 404);
        }

        $request->validate(['coach_id' => 'present|nullable|integer']);

        $newCoach = null;
        if ($request->coach_id !== null) {
            $newCoach = User::whereIn('user_type', ['coach', 'admin'])
                ->where('status', 'active')
                ->find($request->coach_id);
            if (!$newCoach) {
                return json_message_response('El entrenador elegido no existe o no está activo.', 422);
            }
        }

        $previous = $client->coach_id ? User::find($client->coach_id) : null;
        $client->coach_id = $newCoach?->id; // coach_id no es asignable en masa (no está en $fillable)
        $client->save();

        AuditLogger::log(
            'update',
            'users',
            $client->id,
            sprintf(
                'Entrenador de %s: %s → %s.',
                $client->email,
                $previous ? $this->staffName($previous) : 'sin entrenador',
                $newCoach ? $this->staffName($newCoach) : 'sin entrenador'
            )
        );

        return json_custom_response(['data' => $this->coachPayload($client->fresh())]);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return json_message_response('User not found.', 404);
        }

        if ($request->filled('phone_number')) {
            $request->merge(['phone_number' => str_replace('+', '', $request->phone_number)]);
        }

        $request->validate([
            'first_name'   => 'sometimes|required|string|max:255',
            'last_name'    => 'sometimes|required|string|max:255',
            'email'        => 'sometimes|required|email|unique:users,email,' . $id,
            'username'     => 'sometimes|required|unique:users,username,' . $id,
            'phone_number' => 'nullable|string|max:20|unique:users,phone_number,' . $id,
            'gender'       => 'nullable|in:male,female,other',
            'status'       => 'sometimes|in:active,banned,pending',
            'password'     => 'nullable|string|min:8',
            'is_personal_client' => 'sometimes|boolean',
        ]);

        $data = $request->only([
            'first_name', 'last_name', 'email', 'username',
            'phone_number', 'gender', 'status', 'is_personal_client',
        ]);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        $response = [
            'message' => 'User updated successfully.',
            'data'    => new UserResource($user),
        ];

        return json_custom_response($response);
    }

    public function destroy($id)
    {
        $user = User::where('id', $id)->where('user_type', 'user')->first();

        if (!$user) {
            return json_message_response('User not found.', 404);
        }

        $user->delete();

        return json_message_response('User deleted successfully.');
    }

    public function graph(Request $request)
    {
        $user = User::with('userGraph')->find($request->user_id);

        if (!$user) {
            return json_message_response('User not found.', 404);
        }

        $graphs = $user->userGraph()->orderBy('id', 'desc')->paginate(
            $request->get('per_page', 10)
        );

        return json_custom_response([
            'pagination' => json_pagination_response($graphs),
            'data'       => $graphs,
        ]);
    }

    /**
     * Export de informe de usuarios (item 8, auditoria de migracion
     * 2026-09-11) -- mismo UserReportExport que ya usa
     * UserController::downloadUserReport/downloadUserReportPdf (Blade).
     */
    public function report(Request $request)
    {
        $fileType = $request->get('format', 'xlsx');
        $userData = User::userReport()->get();
        $export = new UserReportExport($userData, $request);

        if ($fileType === 'pdf') {
            $collection = $export->collection();
            $mappedData = $collection->map([$export, 'map']);
            $headings = $export->headings();

            $pdf = Pdf::loadView('users.user-report', [
                'headings'   => $headings,
                'mappedData' => $mappedData,
            ])->setPaper('a4', 'landscape');

            return $pdf->download('user-report.pdf');
        }

        $format = match (strtolower($fileType)) {
            'csv'   => \Maatwebsite\Excel\Excel::CSV,
            'xls'   => \Maatwebsite\Excel\Excel::XLS,
            'ods'   => \Maatwebsite\Excel\Excel::ODS,
            'html'  => \Maatwebsite\Excel\Excel::HTML,
            default => \Maatwebsite\Excel\Excel::XLSX,
        };

        return Excel::download($export, 'user-report_' . now()->format('Y-m-d') . '.' . $fileType, $format);
    }
}
