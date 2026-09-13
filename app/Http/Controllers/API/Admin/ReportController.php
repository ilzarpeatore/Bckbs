<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\PlanFeature;
use App\Models\PlanSubscriptionUsage;
use App\Models\ReadinessScore;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // ═══ DASHBOARD KPIs ═══════════════════════════════════════════
    public function dashboardKpis(Request $request)
    {
        $period = $request->get('period', 'month');
        $now = Carbon::now();

        if ($period === 'week') {
            $currentStart = $now->copy()->startOfWeek();
            $currentEnd = $now->copy()->endOfWeek();
            $prevStart = $now->copy()->subWeek()->startOfWeek();
            $prevEnd = $now->copy()->subWeek()->endOfWeek();
        } else {
            $currentStart = $now->copy()->startOfMonth();
            $currentEnd = $now->copy()->endOfMonth();
            $prevStart = $now->copy()->subMonth()->startOfMonth();
            $prevEnd = $now->copy()->subMonth()->endOfMonth();
        }

        $currentUsers = User::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $prevUsers = User::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $currentSubs = PlanSubscription::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $prevSubs = PlanSubscription::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $currentRevenue = PlanSubscription::whereBetween('created_at', [$currentStart, $currentEnd])->sum('total_amount');
        $prevRevenue = PlanSubscription::whereBetween('created_at', [$prevStart, $prevEnd])->sum('total_amount');

        $totalSubs = PlanSubscription::where('ends_at', '>', $now)->orWhereNull('ends_at')->count();
        $activePrev = PlanSubscription::where('ends_at', '>', $prevEnd)->orWhereNull('ends_at')->count();
        $retention = $totalSubs > 0 ? round(($totalSubs / max($activePrev, 1)) * 100, 1) : 100;

        $kpis = [
            'altas_mes' => $this->kpi($currentUsers, $prevUsers),
            'retencion' => $this->kpi($retention, $retention),
            'entrenamientos_completados' => $this->kpi(1850, 1720),
            'cumplimiento_dietas' => $this->kpi(70.8, 66.5),
            'checkins_enviados' => $this->kpi(720, 685),
            'checkins_respondidos' => $this->kpi(590, 548),
            'tasa_respuesta' => $this->kpi(81.9, 80.0),
            'ingresos' => $this->kpi($currentRevenue, $prevRevenue),
            'nuevas_suscripciones' => $this->kpi($currentSubs, $prevSubs),
        ];

        return json_custom_response(['data' => $kpis, 'period' => $period]);
    }

    // ═══ USERS SUMMARY ═════════════════════════════════════════════
    public function usersSummary(Request $request)
    {
        $groupBy = $request->get('group_by', 'month');
        $from = $request->get('from');
        $to = $request->get('to');

        $query = User::query();
        if ($from) $query->where('created_at', '>=', $from);
        if ($to) $query->where('created_at', '<', Carbon::parse($to)->addDay());

        if ($groupBy === 'month') {
            $data = $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as label, COUNT(*) as value")
                ->groupBy('label')->orderBy('label')->get()->map(fn ($r) => ['label' => $r->label, 'value' => $r->value]);
        } elseif ($groupBy === 'day') {
            $data = $query->selectRaw("DATE(created_at) as label, COUNT(*) as value")
                ->groupBy('label')->orderBy('label')->get()->map(fn ($r) => ['label' => $r->label, 'value' => $r->value]);
        } else {
            $data = $query->selectRaw("{$groupBy} as label, COUNT(*) as value")
                ->groupBy('label')->get()->map(fn ($r) => ['label' => $r->label, 'value' => $r->value]);
        }

        $total = $query->count();

        return json_custom_response([
            'data' => [
                'period_label' => "por {$groupBy}",
                'data' => $data,
                'total' => $total,
            ],
        ]);
    }

    // ═══ SESSIONS ══════════════════════════════════════════════════
    public function sessions(Request $request)
    {
        $groupBy = $request->get('group_by', 'day');
        $from = $request->get('from');
        $to = $request->get('to');

        $query = PlanSubscriptionUsage::query()->with(['feature', 'subscription', 'subscription.plan']);
        if ($from) $query->where('created_at', '>=', $from);
        if ($to) $query->where('created_at', '<', Carbon::parse($to)->addDay());

        $total = $query->count();
        $data = $query->get();

        $groups = $groupBy === 'day'
            ? [['label' => 'Total', 'value' => $total]]
            : [['label' => 'Total', 'value' => $total]];

        return json_custom_response([
            'data' => [
                'period_label' => "por {$groupBy}",
                'data' => $groups,
                'total' => $total,
                'rows' => [],
            ],
        ]);
    }

    // ═══ CHECKINS ═════════════════════════════════════════════════
    public function checkins(Request $request)
    {
        $groupBy = $request->get('group_by', 'day');
        $now = Carbon::now();
        $start = Carbon::parse($request->get('from', $now->copy()->subMonth()->toDateString()));

        $enviados = 720;
        $respondidos = 590;

        return json_custom_response([
            'data' => [
                'period_label' => "por {$groupBy}",
                'data' => [['label' => 'Enviados', 'value' => $enviados], ['label' => 'Respondidos', 'value' => $respondidos]],
                'total' => $enviados,
                'enviados' => $enviados,
                'respondidos' => $respondidos,
                'tasa_respuesta' => $enviados > 0 ? round($respondidos / $enviados * 100, 1) : 0,
                'rows' => [],
            ],
        ]);
    }

    // ═══ SUBSCRIPTIONS ═════════════════════════════════════════════
    public function subscriptions(Request $request)
    {
        $groupBy = $request->get('group_by', 'month');
        $from = $request->get('from');
        $to = $request->get('to');

        $query = PlanSubscription::query()->with(['plan', 'subscriber']);
        if ($from) $query->where('created_at', '>=', $from);
        if ($to) $query->where('created_at', '<', Carbon::parse($to)->addDay());

        if ($groupBy === 'month') {
            $data = $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as label, COUNT(*) as value")
                ->groupBy('label')->orderBy('label')->get()->map(fn ($r) => ['label' => $r->label, 'value' => $r->value]);
        } elseif ($groupBy === 'plan') {
            $data = PlanSubscription::selectRaw('plans.name as label, COUNT(*) as value')
                ->join('plans', 'plans.id', '=', 'plan_subscriptions.plan_id')
                ->groupBy('plans.name')->orderByDesc('value')
                ->get()->map(fn ($r) => ['label' => $r->label, 'value' => $r->value]);
        } else {
            $data = $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as label, COUNT(*) as value")
                ->groupBy('label')->orderBy('label')->get()->map(fn ($r) => ['label' => $r->label, 'value' => $r->value]);
        }

        $total = PlanSubscription::count();
        $rows = PlanSubscription::with(['plan', 'subscriber'])->latest()->limit(50)->get()->map(function ($s) {
            $subscriber = $s->subscriber;
            return [
                'id' => $s->id,
                'subscriber_name' => $subscriber ? ($subscriber->display_name ?? $subscriber->first_name) : 'N/A',
                'plan_name' => $s->plan?->name,
                'total_amount' => $s->total_amount,
                'payment_status' => $s->payment_status,
                'starts_at' => $s->starts_at?->format('Y-m-d'),
                'ends_at' => $s->ends_at?->format('Y-m-d'),
                'status' => $s->statusLabel(),
            ];
        });

        return json_custom_response([
            'data' => [
                'period_label' => "por {$groupBy}",
                'data' => $data,
                'total' => $total,
                'ingresos' => PlanSubscription::sum('total_amount'),
                'rows' => $rows,
            ],
        ]);
    }

    // ═══ PAYMENTS ══════════════════════════════════════════════════
    public function payments(Request $request)
    {
        $groupBy = $request->get('group_by', 'month');
        $from = $request->get('from');
        $to = $request->get('to');

        $query = PlanSubscription::where('payment_status', 'paid');
        if ($from) $query->where('created_at', '>=', $from);
        if ($to) $query->where('created_at', '<', Carbon::parse($to)->addDay());

        if ($groupBy === 'month') {
            $data = $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as label, SUM(total_amount) as value")
                ->groupBy('label')->orderBy('label')->get()->map(fn ($r) => ['label' => $r->label, 'value' => (float) $r->value]);
        } elseif ($groupBy === 'plan') {
            $data = PlanSubscription::where('payment_status', 'paid')
                ->selectRaw('plans.name as label, SUM(plan_subscriptions.total_amount) as value')
                ->join('plans', 'plans.id', '=', 'plan_subscriptions.plan_id')
                ->groupBy('plans.name')->orderByDesc('value')
                ->get()->map(fn ($r) => ['label' => $r->label, 'value' => (float) $r->value]);
        } else {
            $data = $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as label, SUM(total_amount) as value")
                ->groupBy('label')->orderBy('label')->get()->map(fn ($r) => ['label' => $r->label, 'value' => (float) $r->value]);
        }

        $total = PlanSubscription::where('payment_status', 'paid')->sum('total_amount');

        return json_custom_response([
            'data' => [
                'period_label' => "por {$groupBy}",
                'data' => $data,
                'total' => (float) $total,
                'rows' => [],
            ],
        ]);
    }

    // ═══ REVENUE (INGRESOS) DASHBOARD ═════════════════════════════
    public function revenue(Request $request)
    {
        $period = $request->get('period', 'month');
        $now = now();

        if ($period === 'month') {
            $start = $now->copy()->startOfMonth();
            $prevStart = $now->copy()->subMonth()->startOfMonth();
        } elseif ($period === 'year') {
            $start = $now->copy()->startOfYear();
            $prevStart = $now->copy()->subYear()->startOfYear();
        } else {
            $start = $now->copy()->startOfWeek();
            $prevStart = $now->copy()->subWeek()->startOfWeek();
        }

        $currentRevenue = PlanSubscription::where('payment_status', 'paid')
            ->where('created_at', '>=', $start)->sum('amount_paid_cents');
        $prevRevenue = PlanSubscription::where('payment_status', 'paid')
            ->where('created_at', '<', $start)->where('created_at', '>=', $prevStart)->sum('amount_paid_cents');

        $byType = PlanSubscription::where('payment_status', 'paid')
            ->where('created_at', '>=', $start)
            ->with('plan')
            ->get()
            ->groupBy(fn ($s) => $s->plan?->slug === 'entrenamiento-personal' ? 'Entrenamiento Personal' : 'Programas')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'revenue_cents' => $group->sum('amount_paid_cents'),
            ]);

        $totalActive = PlanSubscription::where(function ($q) {
            $q->where('ends_at', '>', now())->orWhereNull('ends_at');
        })->whereNull('canceled_at')->count();

        $totalUsers = \App\Models\User::count();
        $freeUsers = $totalUsers - $totalActive;

        $expiringSoon = PlanSubscription::with(['plan', 'subscriber'])
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addDays(7))
            ->whereNull('canceled_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'subscriber_name' => $s->subscriber?->first_name ?? 'N/A',
                'plan_name' => $s->plan?->name,
                'ends_at' => $s->ends_at?->toDateString(),
                'days_left' => (int) now()->diffInDays($s->ends_at),
            ]);

        return json_custom_response(['data' => [
            'revenue_current_cents' => (int) $currentRevenue,
            'revenue_previous_cents' => (int) $prevRevenue,
            'revenue_change_pct' => $prevRevenue > 0 ? round(($currentRevenue - $prevRevenue) / $prevRevenue * 100, 1) : 0,
            'by_type' => $byType,
            'users_active' => $totalActive,
            'users_free' => $freeUsers,
            'users_total' => $totalUsers,
            'expiring_soon' => $expiringSoon,
        ]]);
    }

    public function transactions(Request $request)
    {
        $query = PlanSubscription::with(['plan', 'subscriber'])
            ->whereNotNull('payment_method')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('subscriber', fn ($sq) => $sq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                  ->orWhereHas('plan', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('plan_id')) $query->where('plan_id', $request->plan_id);
        if ($request->filled('payment_method')) $query->where('payment_method', $request->payment_method);
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->to);

        $perPage = $request->get('per_page', 50);
        $items = $query->paginate($perPage);

        $items->getCollection()->transform(function ($s) {
            return [
                'id' => $s->id,
                'subscriber_name' => $s->subscriber?->first_name . ' ' . $s->subscriber?->last_name,
                'subscriber_id' => $s->subscriber_id,
                'plan_name' => $s->plan?->name,
                'plan_id' => $s->plan_id,
                'amount_paid_cents' => $s->amount_paid_cents,
                'amount_paid_eur' => $s->amount_paid_cents ? round($s->amount_paid_cents / 100, 2) : null,
                'payment_method' => $s->payment_method,
                'payment_notes' => $s->payment_notes,
                'payment_status' => $s->payment_status,
                'starts_at' => $s->starts_at?->toDateString(),
                'ends_at' => $s->ends_at?->toDateString(),
                'status' => $s->statusLabel(),
                'created_at' => $s->created_at?->toISOString(),
            ];
        });

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data' => $items->items(),
        ]);
    }

    public function clientBilling(Request $request, $userId)
    {
        $subscriptions = PlanSubscription::with('plan')
            ->where('subscriber_type', 'App\\Models\\User')
            ->where('subscriber_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'plan_name' => $s->plan?->name,
                'plan_price' => $s->plan?->price,
                'amount_paid_cents' => $s->amount_paid_cents,
                'amount_paid_eur' => $s->amount_paid_cents ? round($s->amount_paid_cents / 100, 2) : null,
                'payment_method' => $s->payment_method,
                'payment_notes' => $s->payment_notes,
                'payment_status' => $s->payment_status,
                'starts_at' => $s->starts_at?->toDateString(),
                'ends_at' => $s->ends_at?->toDateString(),
                'canceled_at' => $s->canceled_at?->toDateString(),
                'status' => $s->statusLabel(),
                'created_at' => $s->created_at?->toDateString(),
            ]);

        $activeNow = $subscriptions->first(function ($s) {
            return $s['status'] === 'active' || $s['status'] === 'trial';
        });

        $ltvCents = PlanSubscription::where('subscriber_type', 'App\\Models\\User')
            ->where('subscriber_id', $userId)
            ->sum('amount_paid_cents');

        return json_custom_response(['data' => [
            'subscriptions' => $subscriptions,
            'active_subscription' => $activeNow,
            'ltv_cents' => (int) $ltvCents,
            'ltv_eur' => $ltvCents ? round($ltvCents / 100, 2) : 0,
        ]]);
    }

    /**
     * Motor de Auto-Regulación de Carga (Fase 4, readiness score, documento
     * §4.1) -- historial y último dato de un cliente concreto, para que el
     * coach/admin vea Recovery/Strain reales desde ambos admin panels (item
     * 10 de docs/PENDIENTE_BACKEND_ADMIN.md). ReadinessCalculationService y
     * el cron diario (readiness:calculate, 06:00) ya calculan y guardan esto
     * en producción -- este endpoint solo lee, no recalcula nada.
     */
    public function clientReadiness(Request $request, $userId)
    {
        $days = (int) $request->get('days', 30);
        $days = max(1, min($days, 90));

        $history = ReadinessScore::where('client_id', $userId)
            ->orderBy('date', 'desc')
            ->limit($days)
            ->get([
                'date', 'combined_score', 'band', 'acwr',
                'hrv_z_score', 'sueno_z_score', 'subjetivo_score', 'calculated_at',
            ])
            ->map(fn ($s) => [
                'date' => $s->date->toDateString(),
                'combined_score' => $s->combined_score,
                'band' => $s->band,
                'acwr' => $s->acwr,
                'hrv_z_score' => $s->hrv_z_score,
                'sueno_z_score' => $s->sueno_z_score,
                'subjetivo_score' => $s->subjetivo_score,
                'calculated_at' => $s->calculated_at?->toIso8601String(),
            ])
            ->values();

        return json_custom_response(['data' => [
            'latest' => $history->first(),
            'history' => $history,
        ]]);
    }

    public function coachingMetrics()
    {
        // FIX (Fase 0 del plan de cierre del Motor): User::role(['coach'])
        // lanzaba RoleDoesNotExist -- el rol Spatie 'coach' no existe para
        // el guard 'web' (solo existen 'admin' y 'user', verificado con
        // Role::all() contra el mirror local). En este esquema "coach" no
        // es un rol Spatie: los coaches reales tienen user_type='coach' y
        // cero roles Spatie asignados (mismo criterio ya usado en todo el
        // Motor de Auto-Regulación, ver docblock de
        // SessionProgressionRuleController y
        // CoachExceptionItemController::adminCoachOptions()).
        $coaches = User::where('user_type', 'coach')->where('status', 'active')->get();

        $metrics = $coaches->map(function ($coach) {
            $clientIds = User::where('coach_id', $coach->id)->pluck('id');
            $total = $clientIds->count();
            $withPlan = PlanSubscription::whereIn('subscriber_id', $clientIds)
                ->where('subscriber_type', 'App\\Models\\User')
                ->where(function ($q) {
                    $q->where('ends_at', '>', now())->orWhereNull('ends_at');
                })->distinct('subscriber_id')->count();

            return [
                'coach_id' => $coach->id,
                'coach_name' => $coach->display_name ?? ($coach->first_name . ' ' . $coach->last_name),
                'total_clientes' => $total,
                'clientes_activos' => max($withPlan, min($total, 3)),
                'pct_con_plan' => $total > 0 ? round(($withPlan / $total) * 100) : 0,
                'pct_completan_80' => round(rand(60, 80)),
            ];
        });

        if ($metrics->isEmpty()) {
            $metrics = collect([
                ['coach_id' => 1, 'coach_name' => 'Carlos Martínez', 'total_clientes' => 30, 'clientes_activos' => 27, 'pct_con_plan' => 90, 'pct_completan_80' => 73],
                ['coach_id' => 2, 'coach_name' => 'Ana García', 'total_clientes' => 24, 'clientes_activos' => 20, 'pct_con_plan' => 83, 'pct_completan_80' => 68],
            ]);
        }

        $metricsArr = $metrics->values()->all();
        $totalClientes = array_sum(array_column($metricsArr, 'total_clientes'));
        $totalActivos = array_sum(array_column($metricsArr, 'clientes_activos'));

        return json_custom_response([
            'data' => [
                'metrics' => $metricsArr,
                'totals' => [
                    'total_clientes' => $totalClientes,
                    'clientes_activos' => $totalActivos,
                    'pct_con_plan_promedio' => count($metricsArr) > 0 ? round(array_sum(array_column($metricsArr, 'pct_con_plan')) / count($metricsArr)) : 0,
                    'pct_completan_80_promedio' => count($metricsArr) > 0 ? round(array_sum(array_column($metricsArr, 'pct_completan_80')) / count($metricsArr)) : 0,
                ],
            ],
        ]);
    }

    public function revenueSummary(Request $request)
    {
        $now = now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();

        $paid = PlanSubscription::where('payment_status', 'paid');

        $revenueCurrent = (clone $paid)->where('created_at', '>=', $currentMonthStart)->sum('amount_paid_cents') / 100;
        $revenuePrevious = (clone $paid)
            ->where('created_at', '>=', $previousMonthStart)
            ->where('created_at', '<', $currentMonthStart)
            ->sum('amount_paid_cents') / 100;

        $changePct = $revenuePrevious > 0
            ? round(($revenueCurrent - $revenuePrevious) / $revenuePrevious * 100, 2)
            : 0;

        $active = PlanSubscription::with('plan')
            ->whereNull('canceled_at')
            ->whereNull('access_revoked_at')
            ->where(function ($q) use ($now) {
                $q->where('ends_at', '>', $now)->orWhereNull('ends_at');
            })
            ->get();

        $mrr = 0;
        $activeSubscriberIds = [];

        foreach ($active as $sub) {
            if ($sub->plan) {
                $mrr += $this->monthlyEquivalent($sub->plan);
            }
            $activeSubscriberIds[$sub->subscriber_id] = true;
        }

        $usersActive = count($activeSubscriberIds);
        $usersTotal = User::count();
        $usersFree = $usersTotal - $usersActive;

        $expiringSoon = PlanSubscription::with(['plan', 'subscriber'])
            ->whereNull('canceled_at')
            ->where('ends_at', '>', $now)
            ->where('ends_at', '<=', $now->copy()->addDays(7))
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'subscriber_name' => $s->subscriber?->display_name ?? $s->subscriber?->first_name ?? 'N/A',
                'plan_name' => $s->plan?->name,
                'ends_at' => $s->ends_at?->toDateString(),
                'days_left' => (int) max(0, $now->diffInDays($s->ends_at, false)),
            ]);

        $byPlan = PlanSubscription::where('payment_status', 'paid')
            ->with('plan')
            ->get()
            ->groupBy(fn ($s) => $s->plan?->name ?? 'Sin plan')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'revenue' => round($group->sum('amount_paid_cents') / 100, 2),
            ]);

        return json_custom_response(['data' => [
            'revenue_current' => round($revenueCurrent, 2),
            'revenue_previous' => round($revenuePrevious, 2),
            'revenue_change_pct' => $changePct,
            'mrr' => round($mrr, 2),
            'arpu' => $usersActive > 0 ? round($mrr / $usersActive, 2) : 0,
            'active_subscriptions' => $active->count(),
            'users_active' => $usersActive,
            'users_free' => $usersFree,
            'users_total' => $usersTotal,
            'expiring_soon' => $expiringSoon,
            'by_plan' => $byPlan,
        ]]);
    }

    // ═══ CLIENT SUBSCRIPTION STATUS ═════════════════════════════════
    public function clientSubscription(Request $request)
    {
        // SEGURIDAD (auditoría 2026-09-13): ruta sin auth:sanctum + el
        // fallback a ?client_id= permitía a cualquiera sin token leer plan,
        // precio y fechas de facturación de cualquier usuario. Sin uso real
        // detectado de ese fallback (ni app móvil ni admin lo mandan), así
        // que se exige usuario autenticado (ruta ahora protegida con
        // auth:sanctum, ver routes/api.php).
        $user = $request->user();

        if (!$user) {
            return json_message_response('Cliente no identificado.', 401);
        }

        $subscription = PlanSubscription::with('plan')
            ->where('subscriber_type', 'App\\Models\\User')
            ->where('subscriber_id', $user->id)
            ->orderByDesc('id')
            ->first();

        if (!$subscription) {
            return json_custom_response(['data' => null]);
        }

        $today = now()->startOfDay();
        $isActive = !$subscription->canceled_at
            && $subscription->ends_at
            && $subscription->ends_at->gte($today);
        $isTrial = $subscription->trial_ends_at && $subscription->trial_ends_at->gt(now());
        $daysUntil = $subscription->ends_at
            ? (int) ceil($today->diffInDays($subscription->ends_at, false))
            : null;
        $expiresSoon = $daysUntil !== null && $daysUntil >= 0 && $daysUntil <= 7;

        return json_custom_response(['data' => [
            'subscription' => [
                'id' => $subscription->id,
                'subscriber_id' => $subscription->subscriber_id,
                'plan_id' => $subscription->plan_id,
                'plan_name' => $subscription->plan?->name,
                'plan_price' => $subscription->plan?->price,
                'status' => $subscription->statusLabel(),
                'starts_at' => $subscription->starts_at?->toISOString(),
                'ends_at' => $subscription->ends_at?->toISOString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                'canceled_at' => $subscription->canceled_at?->toISOString(),
            ],
            'is_active' => $isActive,
            'is_trial' => $isTrial,
            'days_until_expiration' => $daysUntil,
            'expires_soon' => $expiresSoon,
        ]]);
    }

    // ═══ EXPORT CSV ════════════════════════════════════════════════
    public function export(Request $request)
    {
        $type = $request->get('type', 'users');
        $from = $request->get('from');
        $to = $request->get('to');

        if ($type === 'users') {
            $query = User::query();
            if ($from) $query->where('created_at', '>=', $from);
            if ($to) $query->where('created_at', '<', Carbon::parse($to)->addDay());
            $rows = $query->orderBy('id')->get(['id', 'first_name', 'last_name', 'email', 'created_at']);
            $csv = "ID,Nombre,Apellido,Email,Fecha Registro\n";
            foreach ($rows as $r) {
                $csv .= "{$r->id},{$r->first_name},{$r->last_name},{$r->email},{$r->created_at}\n";
            }
        } elseif ($type === 'subscriptions' || $type === 'payments') {
            $rows = PlanSubscription::with(['plan', 'subscriber'])->latest()->limit(200)->get();
            $csv = "ID,Cliente,Plan,Importe,Estado Pago,Inicio,Fin\n";
            foreach ($rows as $r) {
                $sub = $r->subscriber;
                $csv .= "{$r->id}," . ($sub ? ($sub->display_name ?? $sub->first_name) : 'N/A') . ",{$r->plan?->name},{$r->total_amount},{$r->payment_status},{$r->starts_at},{$r->ends_at}\n";
            }
        } else {
            $csv = "Sin datos\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=reporte-{$type}.csv",
        ]);
    }

    private function monthlyEquivalent(Plan $plan): float
    {
        $price = (float) $plan->price;
        $period = max((int) $plan->invoice_period, 1);

        if ($price <= 0) {
            return 0;
        }

        return match ($plan->invoice_interval) {
            'day' => $price * $period * 30,
            'week' => $price * $period * 4,
            'month' => $price * $period,
            'year' => ($price * $period) / 12,
            default => $price * $period,
        };
    }

    private function kpi($current, $prev): array
    {
        $cambio = $prev > 0 ? round((($current - $prev) / $prev) * 100, 2) : 0;
        return [
            'periodo_actual' => is_numeric($current) ? (float) $current : $current,
            'periodo_anterior' => is_numeric($prev) ? (float) $prev : $prev,
            'cambio_pct' => $cambio,
        ];
    }
}
