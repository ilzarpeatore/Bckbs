<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Laravel\Sanctum\HasApiTokens;
use Modules\Frontend\Models\UserPreference;
use App\Traits\DailyPlanTrait;

class User extends Authenticatable implements MustVerifyEmail, HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, InteractsWithMedia;
    use DailyPlanTrait;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'username', 'first_name', 'last_name', 'phone_number', 'status', 'email', 'password', 'gender', 'display_name', 'login_type', 'user_type', 'player_id', 'is_subscribe', 'is_personal_client', 'timezone','last_notification_seen', 'apple_user_identifier', 'two_factor_enabled', 'two_factor_secret', 'two_factor_backup_codes', 'two_factor_confirmed_at' ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_subscribe'  => 'integer',
        'is_personal_client' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'two_factor_backup_codes' => 'array',
        'two_factor_confirmed_at' => 'datetime',
    ];

    // SEGURIDAD (auditoria 2026-09-01, HIGH-1): progress_photos son fotos
    // corporales/de salud -- deben vivir en el disco 'private' (fuera del
    // document root servido por Caddy), nunca en 'public'. Ver
    // ProgressPhotoController y SECURITY_AUDIT_BACKEND.md.
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('progress_photos')->useDisk('private');
    }

    public function userProfile() {
        return $this->hasOne(UserProfile::class, 'user_id', 'id');
    }

    // AÑADIDO: Onboarding v2, etapas 2-4 (ver docs/ONBOARDING_V2.md) -- una
    // fila por usuario, igual que userProfile().
    public function parQAnswer()
    {
        return $this->hasOne(ParQAnswer::class, 'user_id', 'id');
    }

    public function trainingQuestionnaireAnswer()
    {
        return $this->hasOne(TrainingQuestionnaireAnswer::class, 'user_id', 'id');
    }

    public function nutritionQuestionnaireAnswer()
    {
        return $this->hasOne(NutritionQuestionnaireAnswer::class, 'user_id', 'id');
    }

    public function userGraph(){
        return $this->hasMany(UserGraph::class, 'user_id', 'id');
    }

    public function userAssignDiet(){
        return $this->hasMany(AssignDiet::class, 'user_id', 'id');
    }

    public function userAssignWorkout(){
        return $this->hasMany(AssignWorkout::class, 'user_id', 'id');
    }

    public function userFavouriteDiet(){
        return $this->hasMany(UserFavouriteDiet::class, 'user_id', 'id');
    }

    public function userFavouriteWorkout(){
        return $this->hasMany(UserFavouriteWorkout::class, 'user_id', 'id');
    }

    public function userNotification(){
        return $this->hasMany(Notification::class, 'notifiable_id', 'id');
    }

    public function chatgptFitBot(){
        return $this->hasMany(ChatgptFitBot::class, 'user_id', 'id');
    }

    public function userPreference()
    {
        return $this->hasMany(UserPreference::class, 'user_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($row) {
            switch ($row->user_type) {
                case 'user':
                    $row->userProfile()->delete();
                    $row->userGraph()->delete();
                    $row->userAssignDiet()->delete();
                    $row->userAssignWorkout()->delete();
                    $row->userFavouriteDiet()->delete();
                    $row->userFavouriteWorkout()->delete();
                    $row->userNotification()->delete();
                    $row->chatgptFitBot()->delete();
                    $row->posting()->delete();
                    $row->postingBookmark()->delete();
                    $row->comment()->delete();
                    $row->postingLike()->delete();
                    $row->reportPosting()->delete();
                    $row->clientNotes()->delete();
                    $row->authoredTasks()->delete();
                    $row->assignedTasks()->delete();
                    // AÑADIDO: Onboarding v2 -- las 3 tablas de cuestionario
                    // ya tienen FK con onDelete('cascade') a nivel de BD,
                    // pero se borran también aquí explícitamente, igual que
                    // el resto de esta lista manual.
                    $row->parQAnswer()->delete();
                    $row->trainingQuestionnaireAnswer()->delete();
                    $row->nutritionQuestionnaireAnswer()->delete();

                break;
                default:
                    # code...
                break;
            }
        });

        static::updated(function($model) {
            if ($model->isDirty('first_name') || $model->isDirty('last_name') ) {
                $model->display_name = $model->first_name.' '.$model->last_name;
                $model->saveQuietly(); 
            }
            if ($model->wasChanged('gender')) {
                $model->refresh();
                self::resetDailyPlan($model);
            }
            // Motor de Auto-Regulación de Carga (2026-08-12): al pasar a
            // cliente 1:1, su historial de sesiones ya existente (mientras
            // era free) nunca generó exercise_session_metrics -- sin esto,
            // el motor arrancaría de cero pese a tener años de datos reales.
            // Único punto de enganche real (Admin\UserController::update()
            // Y API\UserController::register() con invite code escriben
            // is_personal_client por separado -- un observer aquí cubre
            // ambos sin duplicar el hook).
            if ($model->wasChanged('is_personal_client') && $model->is_personal_client) {
                \App\Jobs\BackfillClientSessionHistory::dispatch($model);
            }
        });
    }

    public function routeNotificationForOneSignal()
    {
        return $this->player_id;
    }

    public function subscriptionPackage(){
        return $this->hasOne(Subscription::class, 'user_id','id')->where('status',config('constant.SUBSCRIPTION_STATUS.ACTIVE'));
    }

    // AÑADIDO: a diferencia de subscriptionPackage() (hasOne, una sola suscripción
    // "de membresía" activa), un cliente free puede tener varios Package de contenido
    // (entrenamiento/nutrición) comprados y activos en paralelo.
    // DEPRECADO 2026-08-13: Package/Subscription es el sistema viejo, sin caller real
    // hoy (el admin concede acceso vía Plan/PlanSubscription desde hace tiempo,
    // PlanSubscriptionController::grantPlan()). Se deja el método por si algo legacy
    // lo sigue leyendo directamente, pero el gate real ahora es activePlanSubscriptions().
    public function activePackageSubscriptions()
    {
        return $this->hasMany(Subscription::class, 'user_id', 'id')
            ->where('status', config('constant.SUBSCRIPTION_STATUS.ACTIVE'))
            ->where('payment_status', 'paid')
            ->where(function ($q) {
                $q->whereNull('subscription_end_date')
                  ->orWhere('subscription_end_date', '>=', now());
            });
    }

    // AÑADIDO 2026-08-13: gate real de acceso a contenido (PackageAccessService)
    // — antes leía activePackageSubscriptions() (Package/Subscription, sistema
    // viejo) mientras el único botón real de conceder acceso (admin, grantPlan())
    // escribía en PlanSubscription — desconectados entre sí, bug real confirmado
    // (un Plan con grants_full_recipe_library=true concedido de verdad no
    // desbloqueaba nada). subscriber es MorphTo en PlanSubscription
    // ('subscriber_type'/'subscriber_id'), de ahí el morphMany aquí.
    public function activePlanSubscriptions()
    {
        return $this->morphMany(PlanSubscription::class, 'subscriber')
            ->where('payment_status', 'paid')
            ->whereNull('canceled_at')
            ->whereNull('access_revoked_at')
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            });
    }

    /**
     * Nivel de acceso a contenido del cliente (no confundir con user_type: admin/coach/user).
     * Calculado siempre en vivo, nunca guardado en BD -> refleja el estado real de sus
     * Subscriptions en cada request, sin desincronizarse si una expira.
     * - personal: is_personal_client=true, acceso total sin pagar por Package.
     * - subscriber: tiene al menos 1 Subscription activa+pagada a algun Package
     *   (el contenido especifico que desbloquea sigue decidiendolo PackageAccessService
     *   por Package concreto, esto es solo para gatear features nuevas no ligadas a un
     *   item especifico).
     * - free: por defecto.
     */
    // AÑADIDO: Motor de Auto-Regulación de Carga (Fase 1) — resolver el
    // coach de un cliente para notificaciones (ej. alerta de dolor).
    // coach_id ya existe en la tabla (self-referencing FK, ver migración
    // add_coach_id_to_users_table).
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function getAccessTierAttribute(): string
    {
        if ($this->is_personal_client) {
            return 'personal';
        }

        if ($this->activePlanSubscriptions()->exists()) {
            return 'subscriber';
        }

        return 'free';
    }

    public function classSchedulePlan(){
        return $this->hasMany(ClassSchedulePlan::class, 'user_id', 'id');
    }

    public function setPhoneNumberAttribute($value)
    {
        $this->attributes['phone_number'] = $value ? str_replace('+', '', $value) : null;
    }

    public function getPhoneNumberAttribute()
    {
        return ($this->attributes['phone_number'] ?? null) ? '+' . $this->attributes['phone_number'] : null;
    }

    public function posting()
    {
        return $this->hasMany(Posting::class, 'user_id', 'id');
    }

    public function postingBookmark()
    {
        return $this->hasMany(PostingBookmark::class, 'user_id', 'id');
    }

    public function postingLike()
    {
        return $this->hasMany(PostingLike::class, 'user_id', 'id');
    }

    public function comment()
    {
        return $this->hasMany(Comment::class, 'user_id', 'id');
    }

    public function reportPosting() {
        return $this->hasMany(ReportPosting::class, 'user_id', 'id');
    }

    public function clientNotes()
    {
        return $this->hasMany(ClientNote::class, 'client_id');
    }

    public function tags()
    {
        return $this->belongsToMany(ClientTag::class, 'client_tag_assignments', 'client_id', 'tag_id');
    }

    public function authoredTasks()
    {
        return $this->hasMany(Task::class, 'author_id');
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'client_id');
    }

    public function scopeUserReport($query)
    {
        return $query->where('user_type', 'user')
            ->when(request()->filled('from_date'), function ($q) {
                $q->whereDate('created_at', '>=', request('from_date'));
            })->when(request()->filled('to_date'), function ($q) {
                $q->whereDate('created_at', '<=', request('to_date'));
            })->when(request()->filled('user_id'), function ($q) {
                $q->where('id', request('user_id'));
            });
    }

    public function scopeIsStatus($query, $status = 'active')
    {
        return $query->where('status', $status);
    }

    public function statusMessage()
    {
        switch ($this->status) {
            case 'banned':
                return __('message.account_banned');
            case 'pending':
                return __('message.account_pending');
            default:
                return __('message.account_inactive');
        }
    }
}
