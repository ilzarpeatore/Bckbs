<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Traits\SubscriptionTrait;
use App\Http\Resources\UserDetailResource;
use Nwidart\Modules\Facades\Module;
use Modules\Frontend\Models\Pages;
use Modules\Frontend\Http\Resources\PagesResource;
use App\Models\UserProfile;
use App\Http\Resources\UserProfileResource;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\PersonalClientInvite;
use App\Services\WelcomeMailService;
use Illuminate\Support\Facades\Log;
use App\Models\WorkoutTemplate;
use App\Models\TrainingProgram;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Services\CalendarDateMapper;
use Carbon\Carbon;
use App\Models\Posting;
use App\Models\WorkoutSessionReview;

class UserController extends Controller
{
    use SubscriptionTrait;

    /**
     * AÑADIDO 2026-07-30 — Niveles de acceso: valida un código de invitación
     * de cliente personal (sin registrar nada todavía, solo lectura) para que
     * la pantalla de registro pueda confirmar/prellenar antes de enviar el
     * formulario. Ruta pública (sin auth), igual que login/register.
     */
    public function checkInviteCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $invite = PersonalClientInvite::where('code', strtoupper(trim($request->code)))->first();

        if (!$invite) {
            return json_custom_response(['data' => ['valid' => false, 'reason' => 'not_found']]);
        }
        if ($invite->isUsed()) {
            return json_custom_response(['data' => ['valid' => false, 'reason' => 'already_used']]);
        }
        if ($invite->isExpired()) {
            return json_custom_response(['data' => ['valid' => false, 'reason' => 'expired']]);
        }

        return json_custom_response(['data' => [
            'valid'      => true,
            'first_name' => $invite->first_name,
            'last_name'  => $invite->last_name,
            'email'      => $invite->email,
        ]]);
    }

    public function register(UserRequest $request)
    {
        $input = $request->all();

        $password = $input['password'];
        // SEGURIDAD (auditoria 2026-09-01): user_type y status NUNCA deben
        // venir del cliente -- antes se podia registrar una cuenta admin
        // enviando {"user_type":"admin"} en el body. Ver
        // SECURITY_AUDIT_BACKEND.md CRIT-2.
        $input['user_type'] = 'user';
        $input['password'] = Hash::make($password);

        $input['status'] = 'active';
        if( request('player_id') == "nil"){
            $input['player_id'] = NULL;
        }
        $input['display_name'] = $input['first_name']." ".$input['last_name'];

        // AÑADIDO 2026-07-30 — código de invitación de cliente personal:
        // si es válido, la cuenta queda is_personal_client=true de inmediato,
        // sin que el coach tenga que entrar luego a activarlo a mano.
        $invite = null;
        if ($request->filled('invite_code')) {
            $invite = PersonalClientInvite::where('code', strtoupper(trim($request->invite_code)))->first();

            if (!$invite || !$invite->isValid()) {
                return json_message_response('This invitation code is invalid or has already been used.', 422);
            }
            $input['is_personal_client'] = true;
        }

        $user = User::create($input);
        $user->assignRole($input['user_type']);

        if ($invite) {
            $invite->update(['used_by_user_id' => $user->id, 'used_at' => now()]);
        }

        WelcomeMailService::sendFor($user);

        if( $request->has('user_profile') && $request->user_profile != null ) {
            $user->userProfile()->create($request->user_profile);
        }

        // Auto-asignar formularios estándar (ej. "Perfil y Salud Inicial")
        // a todo cliente nuevo, sin que un coach tenga que asignarlos uno
        // por uno desde el panel Admin. Solo aplica a clientes ("user"),
        // no a admins/coaches creados vía este mismo endpoint.
        if ($input['user_type'] === 'user') {
            Form::autoAssignAllClients()->get()->each(function ($form) use ($user) {
                FormAssignment::firstOrCreate(
                    ['form_id' => $form->id, 'client_id' => $user->id],
                    ['active' => true]
                );
            });

            // AÑADIDO: entrenamiento de bienvenida en el calendario del
            // cliente nuevo desde el primer día — ver assignDemoWorkoutIfNeeded().
            $this->assignDemoWorkoutIfNeeded($user);
        }

        $user->api_token = $user->createToken('auth_token')->plainTextToken;
        $user->profile_image = getSingleMedia($user, 'profile_image', null);
        $user->onboarding_completed = $user->onboarding_completed_at !== null;
        unset($user->roles);
        // SEGURIDAD (re-auditoria 2026-09-01, hallazgo LOW post-HIGH-1):
        // getSingleMedia() carga la relacion `media` como efecto colateral,
        // que de lo contrario se serializaria entera (todas las colecciones,
        // incluida progress_photos) en esta respuesta. login() ya hacia este
        // unset(); register() no. Ver SECURITY_AUDIT_BACKEND.md.
        unset($user->media);

        $message = __('message.save_form',['form' => __('message.'.$input['user_type']) ]);
        $response = [
            'message' => $message,
            'data' => $user
        ];

        return json_custom_response($response);
    }

    /**
     * AÑADIDO: "Tu entrenamiento de hoy" desde el primer día — asigna la
     * plantilla de bienvenida (DemoWorkoutTemplateSeeder, marcada
     * is_demo=true) al calendario PERSONAL del cliente nuevo, en la fecha
     * de hoy. Reutiliza exactamente el mismo mecanismo que ya usa el
     * calendario real (TrainingProgram is_personal + ProgramClientAssignment
     * + ProgramDayAssignment, ver ClientProfileCalendarController::
     * getOrCreatePersonalProgram()) — no una tabla ni una relación nueva,
     * así que si el coach asigna después algo más a este cliente desde el
     * panel Admin, cae en el mismo programa personal en vez de crear uno
     * duplicado.
     *
     * Defensivo: comprueba que el usuario no tenga ya ningún
     * ProgramDayAssignment real (con workout asignado) antes de tocar
     * nada — en un alta nueva esto siempre es cierto, pero por si el flujo
     * de registro cambia en el futuro (p.ej. invite codes que ya traigan
     * calendario) no queremos pisar nada.
     */
    private function assignDemoWorkoutIfNeeded(User $user): void
    {
        $hasRealAssignment = ProgramDayAssignment::whereIn(
            'training_program_id',
            ProgramClientAssignment::where('client_id', $user->id)->where('activo', true)->pluck('training_program_id')
        )->whereNotNull('workout_template_id')->exists();

        if ($hasRealAssignment) {
            return;
        }

        $demoTemplate = WorkoutTemplate::where('is_demo', true)->first();
        if (!$demoTemplate) {
            // DemoWorkoutTemplateSeeder no se ha corrido todavía en este
            // entorno — no hay nada que asignar, no rompemos el registro.
            return;
        }

        // Misma fecha ancla que usa el panel Admin para el calendario
        // personal (ClientProfileCalendarController::PERSONAL_ANCHOR_DATE),
        // referenciada directamente para que week_number/day_of_week
        // salgan siempre iguales sea cual sea el sitio que cree el día.
        $anchor = ClientProfileCalendarController::PERSONAL_ANCHOR_DATE;

        $program = TrainingProgram::firstOrCreate(
            ['personal_client_id' => $user->id, 'is_personal' => true],
            [
                'title'        => 'Calendario personal',
                'coach_id'     => $demoTemplate->coach_id,
                'num_weeks'    => 1000,
                'fecha_inicio' => $anchor,
                'activo'       => true,
            ]
        );

        ProgramClientAssignment::firstOrCreate(
            ['training_program_id' => $program->id, 'client_id' => $user->id],
            ['start_date' => $anchor, 'activo' => true]
        );

        $mapper = new CalendarDateMapper();
        $wd = $mapper->toWeekAndDay(Carbon::parse($anchor), Carbon::now());

        ProgramDayAssignment::firstOrCreate(
            [
                'training_program_id' => $program->id,
                'week_number'         => $wd['week_number'],
                'day_of_week'         => $wd['day_of_week'],
            ],
            [
                'workout_template_id' => $demoTemplate->id,
                'scheduled_date'      => now()->toDateString(),
            ]
        );
    }

    public function login(Request $request)
    {
        $email = $request->email ?? $request->input('email');
        $password = $request->password ?? $request->input('password');

        if (!$email || !$password) {
            return json_message_response(__('auth.failed'), 400);
        }

        $user = User::where('email', $email)->first();

        if ($user && Hash::check($password, $user->password)) {

            if( $user->status == 'banned' ) {
                $message = __('message.account_banned');
                return json_message_response($message,400);
            }

            if(request('player_id') != null && request('player_id') != "nil"){
                $user->player_id = request('player_id');
            }
            $user->save();
            // $user->tokens('auth_token')->delete();
            $success = $user;
            $success['api_token'] = $user->createToken('auth_token')->plainTextToken;
            $success['profile_image'] = getSingleMedia($user, 'profile_image', null);
            $success['onboarding_completed'] = $user->onboarding_completed_at !== null;

            unset($success['media']);

            return json_custom_response([ 'data' => $success ], 200 );
        } else{
            $message = __('auth.failed');

            return json_message_response($message,400);
        }
    }

    public function userDetail(Request $request)
    {
        // SEGURIDAD (revision 2026-09-13): esta ruta era publica (sin
        // auth:sanctum, ver routes/api.php) y cogia el `id` directamente
        // del request -- cualquiera podia leer nombre, email, telefono,
        // genero, perfil y suscripcion de CUALQUIER usuario probando IDs
        // (IDOR + PII expuesta sin autenticar). Ahora exige sesion y
        // siempre devuelve el propio usuario autenticado, igual que ya
        // hace updateProfile() (ver CRIT-1 en el historial de auditoria).
        $user = $request->user();

        if(empty($user) || $user->user_type !== 'user') {
            $message = __('message.not_found_entry', ['name' => __('message.user') ]);
            return json_message_response($message,400);
        }

        $user_detail = new UserDetailResource($user);
        $response = [
            'data' => $user_detail,
            'subscription_detail' => $this->subscriptionPlanDetail($user->id),
        ];
        if( $user->player_id == "nil" ) {
            $user->player_id = NULL;
            $user->save();
        }
        return json_custom_response($response);

    }

    public function changePassword(Request $request)
    {
        $user = User::where('id',auth()->id())->first();

        if($user == "") {
            $message = __('message.not_found_entry', ['name' => __('message.user') ]);
            return json_message_response($message,400);
        }

        $hashedPassword = $user->password;

        $match = Hash::check($request->old_password, $hashedPassword);

        $same_exits = Hash::check($request->new_password, $hashedPassword);
        if ($match)
        {
            if($same_exits){
                $message = __('message.old_new_pass_same');
                return json_message_response($message,400);
            }

			$user->fill([
                'password' => Hash::make($request->new_password)
            ])->save();

            $message = __('message.password_change');
            return json_message_response($message,200);
        }
        else
        {
            $message = __('message.valid_password');
            return json_message_response($message,400);
        }
    }

    public function updateProfile(UserRequest $request)
    {
        // SEGURIDAD (auditoria 2026-09-01): antes se podia pasar un `id`
        // ajeno para editar el perfil de OTRO usuario (IDOR), y
        // $request->all() permitia sobreescribir cualquier campo $fillable
        // del modelo (user_type, status, is_personal_client, password...)
        // via mass assignment. Ver SECURITY_AUDIT_BACKEND.md CRIT-1. Este
        // endpoint es exclusivamente para que un usuario edite SU PROPIO
        // perfil -- ningun consumidor real (app movil) envia `id`.
        $user = auth()->user();

        if($user == null){
            return json_message_response(__('message.no_record_found'),400);
        }

        $safeProfileFields = $request->only([
            'username', 'first_name', 'last_name', 'email', 'phone_number',
            'gender', 'display_name', 'timezone',
        ]);

        $user->fill($safeProfileFields)->update();

        if(isset($request->profile_image) && $request->profile_image != null ) {
            $user->clearMediaCollection('profile_image');
            $user->addMediaFromRequest('profile_image')->toMediaCollection('profile_image');
        }

        $user_data = $user->fresh();

        if($user_data->userProfile != null && $request->has('user_profile') ) {
            $user_data->userProfile->fill($request->user_profile)->update();
        } else if( $request->has('user_profile') && $request->user_profile != null ) {
            $user_data->userProfile()->create($request->user_profile);
        }

        $message = __('message.updated');

        unset($user_data['media']);

        $user_resource = new UserDetailResource($user_data);

        $response = [
            'data'      => $user_resource,
            'message'   => $message
        ];
        return json_custom_response( $response );
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if($request->is('api*'))
        {
            $user->player_id = null;
            $user->expo_push_token = null;
            $user->save();
            $user->currentAccessToken()->delete();
            $message = __('message.logout_success');
            return json_message_response($message);
        }
    }

    /**
     * Auditoría de seguridad 2026-08-26: revoca todos los personal access
     * tokens del usuario (todas las sesiones/dispositivos), no solo el
     * actual — para "cerrar sesión en todos los dispositivos" o reaccionar
     * a un token robado.
     */
    public function logoutAllDevices(Request $request)
    {
        $user = Auth::user();
        $user->player_id = null;
        $user->expo_push_token = null;
        $user->save();
        $user->tokens()->delete();
        $message = __('message.logout_success');
        return json_message_response($message);
    }

    /**
     * AÑADIDO 2026-09-11 -- registro del token de Expo Push. El cliente lo
     * llama cada vez que expo-notifications le da un token (login, primer
     * arranque tras conceder permisos, o si Expo lo rota). Independiente de
     * login/register a proposito: un token puede refrescarse a mitad de
     * sesion, sin pasar por login otra vez.
     */
    public function updatePushToken(Request $request)
    {
        $request->validate([
            'expo_push_token' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $user->expo_push_token = $request->expo_push_token;
        $user->save();

        return json_message_response(__('message.updated'));
    }

    public function forgetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $response = Password::sendResetLink(
            $request->only('email')
        );

        return $response == Password::RESET_LINK_SENT
            ? response()->json(['message' => __($response), 'status' => true], 200)
            : response()->json(['message' => __($response), 'status' => false], 400);
    }

    public function socialMailLogin(Request $request)
    {
        $input = $request->all();

        if($request->filled('apple_user_identifier')) {
            $user_data = User::where('apple_user_identifier', $request->apple_user_identifier)->first();
        } else {
            $user_data = User::where('email',$request->email)->first();
        }

        if( $user_data != null ) {
            if( !in_array($user_data->user_type, ['admin',request('user_type')] )) {
                $message = __('auth.failed');
                return json_message_response($message,400);
            }

            if( $user_data->status == 'banned' ) {
                $message = __('message.account_banned');
                return json_message_response($message,400);
            }

            if( !isset($user_data->login_type) || $user_data->login_type  == '' ) {
                if( in_array($request->login_type, ['google', 'apple'] ))
                {
                    $message = __('validation.unique',['attribute' => 'email' ]);
                    return json_message_response($message,400);
                }
            }
            $message = __('message.login_success');
        }
        else
        {
            $validator = Validator::make($input,[
                'email' => 'required|email|unique:users,email',
                'username'  => 'required|unique:users,username',
                'phone_number' => 'nullable|max:20|unique:users,phone_number',
            ]);

            if ( $validator->fails() ) {
                $data = [
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'all_message' =>  $validator->errors()
                ];

                return json_custom_response($data, 422);
            }

            $password = !empty($input['accessToken']) ? $input['accessToken'] : $input['email'];

            $input['display_name'] = $input['first_name']." ".$input['last_name'];
            $input['password'] = Hash::make($password);
            $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'user';
            if( request('player_id') == "nil"){
                $input['player_id'] = NULL;
            }
            $user = User::create($input);

            $user->assignRole($input['user_type']);

            $user_data = $user;
            $message = __('message.save_form',['form' => $input['user_type'] ]);
        }

        // $user_data->tokens('auth_token')->delete();
        $user_data['api_token'] = $user_data->createToken('auth_token')->plainTextToken;
        $user_data['profile_image'] = getSingleMedia($user_data, 'profile_image', null);

        $response = [
            'status'    => true,
            'message'   => $message,
            'data'      => $user_data
        ];
        return json_custom_response($response);
    }

    public function socialOTPLogin(Request $request)
    {
        $input = $request->all();

        $user_data = User::where('username', $input['username'])->where('login_type','mobile')->first();

        if( $user_data != null )
        {
            if( !in_array($user_data->user_type, ['admin',request('user_type')] )) {
                $message = __('auth.failed');
                return json_message_response($message,400);
            }

            if( $user_data->status == 'banned' ) {
                $message = __('message.account_banned');
                return json_message_response($message,400);
            }

            if( !isset($user_data->login_type) || $user_data->login_type  == '' )
            {
                $message = __('validation.unique',['attribute' => 'username' ]);
                return json_message_response($message,400);
            }
            $message = __('message.login_success');
        }
        else
        {
            if($request->login_type === 'mobile' && $user_data == null ){
                $otp_response = [
                    'status' => true,
                    'is_user_exist' => false
                ];
                return json_custom_response($otp_response);
            }

            $validator = Validator::make($input,[
                'email' => 'required|email|unique:users,email',
                'username'  => 'required|unique:users,username',
                'phone_number' => 'max:20|unique:users,phone_number',
            ]);

            if ( $validator->fails() ) {
                $data = [
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'all_message' =>  $validator->errors()
                ];

                return json_custom_response($data, 422);
            }

            $password = !empty($input['accessToken']) ? $input['accessToken'] : $input['email'];
            if( request('player_id') == "nil"){
                $input['player_id'] = NULL;
            }
            $input['display_name'] = $input['first_name']." ".$input['last_name'];
            $input['password'] = Hash::make($password);
            $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'user';
            $user = User::create($input);

            $user->assignRole($input['user_type']);

            $user_data = $user;
            $message = __('message.save_form',['form' => $input['user_type'] ]);
        }
        // $user_data->tokens('auth_token')->delete();
        $user_data['api_token'] = $user_data->createToken('auth_token')->plainTextToken;
        $user_data['profile_image'] = getSingleMedia($user_data, 'profile_image', null);

        $response = [
            'status'    => true,
            'message'   => $message,
            'data'      => $user_data
        ];
        return json_custom_response($response);
    }

    public function updateUserStatus(Request $request)
    {
        $user_id = $request->id ?? auth()->user()->id;

        $user = User::where('id',$user_id)->first();

        if($user == "") {
            $message = __('message.not_found_entry', ['name' => __('message.user') ]);
            return json_message_response($message,400);
        }
        if($request->has('status')) {
            $user->status = $request->status;
        }

        $user->save();


        $user_resource = new UserResource($user);

        $message = __('message.update_form',['form' => __('message.status') ]);
        $response = [
            'data'      => $user_resource,
            'message'   => $message
        ];
        return json_custom_response($response);
    }

    public function getAppSetting(Request $request)
    {
        if($request->has('id') && isset($request->id)){
            $data = AppSetting::where('id',$request->id)->first();
        } else {
            $data = AppSetting::first();
        }

        $app_version = [
            'android_force_update'  => SettingData('APPVERSION', 'APPVERSION_ANDROID_FORCE_UPDATE'),
            'android_version_code'  => SettingData('APPVERSION', 'APPVERSION_ANDROID_VERSION_CODE'),
            'playstore_url'         => SettingData('APPVERSION', 'APPVERSION_PLAYSTORE_URL'),
            'ios_force_update'      => SettingData('APPVERSION', 'APPVERSION_IOS_FORCE_UPDATE'),
            'ios_version'           => SettingData('APPVERSION', 'APPVERSION_IOS_VERSION'),
            'appstore_url'          => SettingData('APPVERSION', 'APPVERSION_APPSTORE_URL'),

        ];
        $crisp_chat =[
            'crisp_chat_website_id' => SettingData('CRISP_CHAT_CONFIGURATION', 'CRISP_CHAT_CONFIGURATION_WEBSITE_ID') ?? null,
            'is_crisp_chat_enabled' => SettingData('CRISP_CHAT_CONFIGURATION', 'CRISP_CHAT_CONFIGURATION_ENABLE/DISABLE') ? true : false,
        ];

        $mobile_game =[
            'mobile_game_enabled' => SettingData('MOBILE_GAME_ENABLE', 'MOBILE_GAME_ENABLE_TYPE') ? true : false,
        ];

        $data['app_version'] = $app_version;
        $data['crisp_chat'] = $crisp_chat;
        $data['mobile_game'] = $mobile_game;

        $data['pages'] = [];
        if( Module::has('Frontend') && Module::isEnabled('Frontend')) {
            $pages = Pages::where('status', 1)->get();
            $data['pages'] = PagesResource::collection($pages);
        }
        $data['subscription'] = SettingData('subscription', 'subscription_system') ?? '1';
        $data['login_enable'] = SettingData('login_enable', 'login_enable') ?? '0';

        return json_custom_response($data);
    }

    /**
     * Borrado de cuenta desde la app (cliente) -- definitivo e inmediato,
     * sin periodo de gracia ni recuperación (obligación de la política de
     * privacidad ya publicada, ver docs/BORRADO_CUENTA_BACKEND.md §2).
     * Registrado tanto en 'delete-user-account' (compat) como en
     * 'v1/delete-account' (URL real que llama el cliente).
     */
    public function deleteUserAccount(Request $request)
    {
        $id = auth()->id();
        $user = User::where('id', $id)->first();

        if (!$user) {
            $message = __('message.not_found_entry', ['name' => __('message.account') ]);
            return json_message_response($message, 404);
        }

        // Guard: esta vía es solo para que un CLIENTE borre su propia cuenta.
        // Un coach con clientes propios o un admin no puede auto-borrarse
        // así -- borrar un coach implica decidir qué pasa con sus clientes
        // activos, gestión que se hace a mano desde el admin panel (ver
        // docs/BORRADO_CUENTA_BACKEND.md §4).
        $isCoachWithClients = User::where('coach_id', $user->id)->exists();
        if ($isCoachWithClients || $user->hasRole('admin')) {
            return json_message_response('Contacta con soporte para dar de baja una cuenta de entrenador', 403);
        }

        // El 'reason' es opcional, solo para contexto del coach -- se
        // registra en el log, no se persiste en ninguna tabla nueva.
        if ($request->filled('reason')) {
            Log::info('Account deletion requested', [
                'user_id' => $user->id,
                'reason'  => $request->input('reason'),
            ]);
        }

        // Revocación explícita de todos los tokens -- cierra sesión en
        // todos los dispositivos al instante, aunque el cascade delete de
        // User::boot() también los borre indirectamente.
        $user->tokens()->delete();

        // Sale de la lista de clientes activos de su coach de inmediato
        // (redundante con el borrado total que sigue justo después, pero
        // explícito por si algo interrumpe el borrado a medias).
        if ($user->coach_id !== null || $user->is_personal_client) {
            $user->coach_id = null;
            $user->is_personal_client = false;
            $user->saveQuietly();
        }

        $user->delete();

        return json_message_response('Cuenta eliminada correctamente.');
    }

    public function userProfileDetail(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->user_type !== 'user') {
            return json_message_response('User not found.', 404);
        }

        $user_detail = new UserDetailResource($user);
        $response = [
            'data' => $user_detail,
            'subscription_detail' => $this->subscriptionPlanDetail($user->id),
        ];

        return json_custom_response($response);
    }

    /**
     * AÑADIDO 2026-08-13 — Perfil de otro usuario (pantalla social
     * other_user_profile_screen.tsx pedía "num. entrenamientos" y "num.
     * posts" y no existía ningún endpoint que los expusiera). Ruta
     * autenticada (dentro del grupo auth:sanctum), a diferencia de
     * `user-detail` (pública) — solo devuelve contadores, sin PII.
     */
    public function userSocialStats(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $userId = $request->user_id;

        $postingCount = Posting::published()->where('user_id', $userId)->count();
        $workoutCount = WorkoutSessionReview::where('user_id', $userId)->whereNotNull('completed_at')->count();

        $response = [
            'data' => [
                'posting_count' => $postingCount,
                'workout_count' => $workoutCount,
            ],
        ];

        return json_custom_response($response);
    }

    public function setReminderSetting(Request $request)
    {
        $user = auth()->user();

        $reminder_settings = [
            'water_reminder_settings',
            'meal_reminder_settings',
        ];

        $update_reminder = [];

        foreach ($reminder_settings as $column) {
            if ($request->has($column)) {
                $update_reminder[$column] = $request->input($column);
         }
        }

        $user_profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $update_reminder
        );

        $user_detail = new UserProfileResource($user_profile);
        
        $response = [
            'data' => $user_detail,
        ];

        return json_custom_response($response);
    }
}
