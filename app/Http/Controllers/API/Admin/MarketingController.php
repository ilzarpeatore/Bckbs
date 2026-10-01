<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\PackCheckoutAttempt;
use App\Models\PackPurchase;
use App\Models\Plan;
use App\Models\WebPageview;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Secciones de marketing del panel (docs/MARKETING_WEB.md): newsletter,
 * mensajes de contacto, cestas abandonadas y analítica propia de la web.
 */
class MarketingController extends Controller
{
    // ─── Newsletter ───────────────────────────────────────────────────

    public function newsletterIndex(Request $request)
    {
        $query = NewsletterSubscriber::query()->latest('id');
        if ($request->filled('search')) {
            $query->where('email', 'like', '%' . mb_strtolower($request->search) . '%');
        }
        foreach (['status', 'source', 'utm_campaign'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->input($f));
            }
        }
        $items = $query->paginate(min(200, (int) $request->get('per_page', 25)));

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data' => $items->getCollection()->map(fn (NewsletterSubscriber $s) => [
                'id' => $s->id,
                'email' => $s->email,
                'source' => $s->source,
                'status' => $s->status,
                'utm_source' => $s->utm_source,
                'utm_campaign' => $s->utm_campaign,
                'referrer_host' => $s->referrer_host,
                'landing_path' => $s->landing_path,
                'confirmed_at' => $s->confirmed_at?->toISOString(),
                'unsubscribed_at' => $s->unsubscribed_at?->toISOString(),
                'created_at' => $s->created_at?->toISOString(),
            ])->values(),
        ]);
    }

    public function newsletterStats()
    {
        $byStatus = NewsletterSubscriber::select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');
        $bySource = NewsletterSubscriber::where('status', NewsletterSubscriber::STATUS_CONFIRMED)
            ->select('source', DB::raw('COUNT(*) as n'))->groupBy('source')->orderByDesc('n')->pluck('n', 'source');
        $daily = NewsletterSubscriber::where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as n'))->groupBy('day')->pluck('n', 'day');

        return json_custom_response(['data' => [
            'confirmed' => (int) ($byStatus[NewsletterSubscriber::STATUS_CONFIRMED] ?? 0),
            'pending' => (int) ($byStatus[NewsletterSubscriber::STATUS_PENDING] ?? 0),
            'unsubscribed' => (int) ($byStatus[NewsletterSubscriber::STATUS_UNSUBSCRIBED] ?? 0),
            'by_source' => $bySource,
            'daily' => $this->fillDays($daily, now()->subDays(29), now()),
        ]]);
    }

    /** CSV para importar en la herramienta de email marketing (por defecto, solo confirmados). */
    public function newsletterExport(Request $request)
    {
        $status = $request->get('status', NewsletterSubscriber::STATUS_CONFIRMED);
        $rows = NewsletterSubscriber::when($status !== 'all', fn ($q) => $q->where('status', $status))->orderBy('id')->get();

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['email', 'estado', 'origen', 'fecha_alta', 'fecha_confirmacion', 'utm_source', 'utm_campaign']);
        foreach ($rows as $s) {
            fputcsv($out, [$s->email, $s->status, $s->source, $s->created_at?->toDateTimeString(), $s->confirmed_at?->toDateTimeString(), $s->utm_source, $s->utm_campaign]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        AuditLogger::log('export_newsletter', 'newsletter_subscribers', null, "Exportados {$rows->count()} suscriptores ({$status}).");

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="newsletter-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    /** Borrado definitivo (derecho de supresión, RGPD). */
    public function newsletterDelete(Request $request)
    {
        $request->validate(['id' => 'required|exists:newsletter_subscribers,id']);
        NewsletterSubscriber::whereKey($request->id)->delete();
        AuditLogger::log('delete_newsletter_subscriber', 'newsletter_subscribers', (int) $request->id, 'Suscriptor eliminado.');

        return json_message_response('Suscriptor eliminado.');
    }

    // ─── Mensajes de contacto ─────────────────────────────────────────

    public function contactIndex(Request $request)
    {
        $query = ContactMessage::query()->latest('id');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('email', 'like', $term)->orWhere('name', 'like', $term)->orWhere('subject', 'like', $term));
        }
        $items = $query->paginate(min(200, (int) $request->get('per_page', 25)));

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'unread' => ContactMessage::where('status', 'new')->count(),
            'data' => $items->getCollection()->map(fn (ContactMessage $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'subject' => $m->subject,
                'message' => $m->message,
                'status' => $m->status,
                'utm_source' => $m->utm_source,
                'utm_campaign' => $m->utm_campaign,
                'referrer_host' => $m->referrer_host,
                'created_at' => $m->created_at?->toISOString(),
            ])->values(),
        ]);
    }

    public function contactUpdate(Request $request)
    {
        $request->validate(['id' => 'required|exists:contact_messages,id', 'status' => 'required|in:new,read,archived']);
        ContactMessage::whereKey($request->id)->update([
            'status' => $request->status,
            'read_at' => $request->status === 'new' ? null : now(),
        ]);

        return json_message_response('Mensaje actualizado.');
    }

    public function contactDelete(Request $request)
    {
        $request->validate(['id' => 'required|exists:contact_messages,id']);
        ContactMessage::whereKey($request->id)->delete();

        return json_message_response('Mensaje eliminado.');
    }

    // ─── Cestas abandonadas ───────────────────────────────────────────

    public function checkoutAttempts(Request $request)
    {
        $query = PackCheckoutAttempt::with('plan:id,name,slug')->latest('id');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }
        if ($request->filled('search')) {
            $query->where('email', 'like', '%' . mb_strtolower($request->search) . '%');
        }
        $items = $query->paginate(min(200, (int) $request->get('per_page', 25)));

        $since = now()->subDays(30);
        $counts = PackCheckoutAttempt::where('created_at', '>=', $since)
            ->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'summary' => [
                'started' => (int) $counts->sum(),
                'completed' => (int) ($counts[PackCheckoutAttempt::STATUS_COMPLETED] ?? 0),
                'abandoned' => (int) ($counts[PackCheckoutAttempt::STATUS_EXPIRED] ?? 0),
                'recovered' => (int) ($counts[PackCheckoutAttempt::STATUS_RECOVERED] ?? 0),
                'in_progress' => (int) ($counts[PackCheckoutAttempt::STATUS_STARTED] ?? 0),
            ],
            'data' => $items->getCollection()->map(fn (PackCheckoutAttempt $a) => [
                'id' => $a->id,
                'plan' => $a->plan ? ['id' => $a->plan->id, 'name' => $a->plan->name] : null,
                'email' => $a->email,
                'status' => $a->status,
                'amount' => $a->amount_cents !== null ? $a->amount_cents / 100 : null,
                'recovery_consent' => $a->recovery_consent,
                'recovery_email_sent_at' => $a->recovery_email_sent_at?->toISOString(),
                'utm_source' => $a->utm_source,
                'utm_campaign' => $a->utm_campaign,
                'referrer_host' => $a->referrer_host,
                'click_id_type' => $a->click_id_type,
                'created_at' => $a->created_at?->toISOString(),
                'expired_at' => $a->expired_at?->toISOString(),
                'completed_at' => $a->completed_at?->toISOString(),
            ])->values(),
        ]);
    }

    // ─── Analítica ────────────────────────────────────────────────────

    public function analytics(Request $request)
    {
        [$from, $to] = $this->range($request);
        $views = fn () => WebPageview::whereBetween('created_at', [$from, $to]);

        // Visitante = hash diario: los "visitantes" de un periodo son la suma
        // de visitantes únicos de cada día (sin cookies no se puede seguir a
        // nadie de un día a otro, y es intencionado).
        $daily = $views()->select(
            DB::raw('DATE(created_at) as day'),
            DB::raw('COUNT(*) as pageviews'),
            DB::raw('COUNT(DISTINCT visitor_hash) as visitors'),
        )->groupBy('day')->get()->keyBy('day');

        $series = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $series[] = [
                'day' => $key,
                'pageviews' => (int) ($daily[$key]->pageviews ?? 0),
                'visitors' => (int) ($daily[$key]->visitors ?? 0),
            ];
        }

        $topPages = $views()->select('path', DB::raw('COUNT(*) as pageviews'), DB::raw('COUNT(DISTINCT visitor_hash) as visitors'))
            ->groupBy('path')->orderByDesc('pageviews')->limit(25)->get();

        // Fuente de cada visitante-día = su primera visita del día.
        $entries = $this->entries($from, $to);
        $sources = $entries->groupBy(fn ($e) => $e->utm_source ?: ($e->referrer_host ?: 'directo'))
            ->map(fn ($g, $k) => ['source' => $k, 'visitors' => $g->count(), 'paid' => $g->whereNotNull('click_id_type')->count()])
            ->sortByDesc('visitors')->values()->take(25);
        $devices = $entries->groupBy(fn ($e) => $e->device ?: 'desconocido')->map->count();
        $clickIds = $entries->whereNotNull('click_id_type')->groupBy('click_id_type')->map->count();

        return json_custom_response(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'pageviews' => array_sum(array_column($series, 'pageviews')),
                'visitors' => array_sum(array_column($series, 'visitors')),
                'from_ads' => $clickIds->sum(),
                'newsletter_signups' => NewsletterSubscriber::whereBetween('created_at', [$from, $to])->count(),
                'checkout_started' => PackCheckoutAttempt::whereBetween('created_at', [$from, $to])->count(),
                'purchases' => PackPurchase::whereBetween('created_at', [$from, $to])->where('status', '!=', PackPurchase::STATUS_REFUNDED)->count(),
            ],
            'series' => $series,
            'top_pages' => $topPages,
            'sources' => $sources,
            'devices' => $devices,
            'ad_clicks' => $clickIds,
            'campaigns' => $this->campaigns($from, $to, $entries),
            'packs' => $this->packFunnel($from, $to),
        ]]);
    }

    /** Primera visita de cada visitante en cada día del rango. */
    private function entries(Carbon $from, Carbon $to): Collection
    {
        $firstIds = WebPageview::whereBetween('created_at', [$from, $to])->whereNotNull('visitor_hash')
            ->select(DB::raw('MIN(id) as id'))->groupBy('visitor_hash', DB::raw('DATE(created_at)'));

        return WebPageview::whereIn('id', $firstIds)->get(['id', 'visitor_hash', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer_host', 'click_id_type', 'device']);
    }

    /** Embudo por campaña: visitantes → compras iniciadas → compras → ingresos, y newsletter. */
    private function campaigns(Carbon $from, Carbon $to, Collection $entries): Collection
    {
        $key = fn ($r) => implode('|', [$r->utm_source ?? '', $r->utm_medium ?? '', $r->utm_campaign ?? '']);
        $rows = [];
        $row = function ($r) use (&$rows, $key) {
            $k = $key($r);
            $rows[$k] ??= [
                'utm_source' => $r->utm_source, 'utm_medium' => $r->utm_medium, 'utm_campaign' => $r->utm_campaign,
                'visitors' => 0, 'checkout_started' => 0, 'purchases' => 0, 'revenue' => 0.0, 'abandoned' => 0, 'newsletter' => 0,
            ];

            return $k;
        };

        foreach ($entries->filter(fn ($e) => $e->utm_source || $e->utm_campaign) as $e) {
            $rows[$row($e)]['visitors']++;
        }
        $attempts = PackCheckoutAttempt::whereBetween('created_at', [$from, $to])
            ->where(fn ($q) => $q->whereNotNull('utm_source')->orWhereNotNull('utm_campaign'))->get();
        foreach ($attempts as $a) {
            $k = $row($a);
            $rows[$k]['checkout_started']++;
            if (in_array($a->status, [PackCheckoutAttempt::STATUS_COMPLETED, PackCheckoutAttempt::STATUS_RECOVERED], true)) {
                $rows[$k]['purchases']++;
                $rows[$k]['revenue'] += ($a->amount_cents ?? 0) / 100;
            }
            if ($a->status === PackCheckoutAttempt::STATUS_EXPIRED) {
                $rows[$k]['abandoned']++;
            }
        }
        $subs = NewsletterSubscriber::whereBetween('created_at', [$from, $to])
            ->where(fn ($q) => $q->whereNotNull('utm_source')->orWhereNotNull('utm_campaign'))->get();
        foreach ($subs as $s) {
            $rows[$row($s)]['newsletter']++;
        }

        return collect(array_values($rows))->sortByDesc('visitors')->values();
    }

    /** Embudo por pack: landing → comprar → pago → registrado en la app → onboarding terminado. */
    private function packFunnel(Carbon $from, Carbon $to): Collection
    {
        return Plan::where('is_pack', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'slug'])->map(function (Plan $plan) use ($from, $to) {
            $attempts = PackCheckoutAttempt::where('plan_id', $plan->id)->whereBetween('created_at', [$from, $to]);
            $purchases = PackPurchase::with('subscription')->where('plan_id', $plan->id)->whereBetween('created_at', [$from, $to])
                ->where('status', '!=', PackPurchase::STATUS_REFUNDED)->get();

            return [
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'landing_visitors' => (int) WebPageview::whereBetween('created_at', [$from, $to])->where('path', "/packs/{$plan->slug}")
                    ->select(DB::raw('COUNT(DISTINCT visitor_hash) as n'))->value('n'),
                'checkout_started' => (clone $attempts)->count(),
                'abandoned' => (clone $attempts)->where('status', PackCheckoutAttempt::STATUS_EXPIRED)->count(),
                'recovered' => (clone $attempts)->where('status', PackCheckoutAttempt::STATUS_RECOVERED)->count(),
                'purchases' => $purchases->count(),
                'revenue' => $purchases->sum('amount_cents') / 100,
                'registered' => $purchases->where('status', PackPurchase::STATUS_CLAIMED)->count(),
                'onboarding_done' => $purchases->filter(fn ($p) => $p->subscription?->fulfilled_at)->count(),
            ];
        });
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : $to->copy()->subDays(29)->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        return [$from, $to];
    }

    private function fillDays(Collection $counts, Carbon $from, Carbon $to): array
    {
        $out = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $out[] = ['day' => $d->toDateString(), 'n' => (int) ($counts[$d->toDateString()] ?? 0)];
        }

        return $out;
    }
}
