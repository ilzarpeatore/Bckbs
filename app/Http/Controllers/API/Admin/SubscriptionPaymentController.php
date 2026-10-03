<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPaymentClient;
use App\Models\SubscriptionPaymentRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seguimiento manual de pagos mensuales (Informes > Seguimiento de pagos).
 * Pensado para clientes de entrenamiento personal (is_personal_client=true)
 * que pagan fuera de la pasarela de la app (transferencia, efectivo, etc.):
 * el coach marca a mano, mes a mes, quién ha pagado y por cuánto. No tiene
 * relación con Plan/PlanSubscription (esos ya se cobran y registran solos).
 *
 * Además de los usuarios de la app, admite clientes "externos"
 * (SubscriptionPaymentClient): gente que paga pero no tiene cuenta. En el
 * listado cada fila lleva `source` = 'user' | 'external'; los ids de cada
 * tipo son independientes.
 */
class SubscriptionPaymentController extends Controller
{
    private const MONTH_LABELS = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    // ═══ LISTADO: clientes x meses del año ═══════════════════════════
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        $clients = User::where('is_personal_client', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'display_name', 'email', 'monthly_fee', 'status']);

        $records = SubscriptionPaymentRecord::whereIn('user_id', $clients->pluck('id'))
            ->where('year', $year)
            ->get()
            ->groupBy('user_id');

        $data = $clients->map(function ($client) use ($records) {
            $tariff = $client->monthly_fee !== null ? (float) $client->monthly_fee : 0.0;

            return [
                'id' => $client->id,
                'source' => 'user',
                'name' => $client->display_name ?: trim("{$client->first_name} {$client->last_name}"),
                'email' => $client->email,
                'status' => $client->status,
                'monthly_fee' => $tariff,
                'months' => $this->buildMonths($records->get($client->id, collect()), $tariff),
            ];
        });

        $externalClients = SubscriptionPaymentClient::orderBy('name')->get();
        $externalRecords = SubscriptionPaymentRecord::whereIn('external_client_id', $externalClients->pluck('id'))
            ->where('year', $year)
            ->get()
            ->groupBy('external_client_id');

        $externalData = $externalClients->map(fn ($client) => [
            'id' => $client->id,
            'source' => 'external',
            'name' => $client->name,
            'email' => $client->email,
            'status' => 'external',
            'monthly_fee' => (float) $client->monthly_fee,
            'months' => $this->buildMonths($externalRecords->get($client->id, collect()), (float) $client->monthly_fee),
        ]);

        $data = $data->concat($externalData)->sortBy(fn ($row) => mb_strtolower($row['name']));

        return json_custom_response([
            'data' => [
                'year' => $year,
                'month_labels' => self::MONTH_LABELS,
                'clients' => $data->values(),
            ],
        ]);
    }

    private function buildMonths($records, float $tariff): array
    {
        $byMonth = $records->keyBy('month');
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $record = $byMonth->get($m);
            $months[$m] = $record
                ? [
                    'paid' => (bool) $record->paid,
                    'amount' => (float) $record->amount,
                    'paid_at' => $record->paid_at?->toDateString(),
                    'notes' => $record->notes,
                ]
                : [
                    'paid' => false,
                    'amount' => $tariff,
                    'paid_at' => null,
                    'notes' => null,
                ];
        }

        return $months;
    }

    // ═══ AÑOS DISPONIBLES (para el selector) ═════════════════════════
    public function years()
    {
        $years = SubscriptionPaymentRecord::selectRaw('DISTINCT year')->pluck('year');
        $years->push(now()->year);

        return json_custom_response(['data' => $years->unique()->sortDesc()->values()]);
    }

    // ═══ ACTUALIZAR TARIFA FIJA DE UN CLIENTE ════════════════════════
    public function updateTariff(Request $request, $userId)
    {
        $validated = $request->validate([
            'monthly_fee' => 'required|numeric|min:0|max:99999.99',
        ]);

        $user = User::find($userId);
        if (!$user) {
            return json_message_response('Cliente no encontrado.', 404);
        }

        $user->monthly_fee = $validated['monthly_fee'];
        $user->save();

        return json_custom_response(['data' => ['id' => $user->id, 'monthly_fee' => (float) $user->monthly_fee]]);
    }

    // ═══ MARCAR / AJUSTAR UN MES CONCRETO ════════════════════════════
    public function updatePayment(Request $request, $userId, $year, $month)
    {
        $user = User::find($userId);
        if (!$user) {
            return json_message_response('Cliente no encontrado.', 404);
        }

        return $this->savePayment($request, ['user_id' => $user->id], (float) ($user->monthly_fee ?? 0), $year, $month);
    }

    public function updateExternalPayment(Request $request, $clientId, $year, $month)
    {
        $client = SubscriptionPaymentClient::find($clientId);
        if (!$client) {
            return json_message_response('Cliente no encontrado.', 404);
        }

        return $this->savePayment($request, ['external_client_id' => $client->id], (float) $client->monthly_fee, $year, $month);
    }

    private function savePayment(Request $request, array $owner, float $tariff, $year, $month)
    {
        $year = (int) $year;
        $month = (int) $month;

        if ($month < 1 || $month > 12) {
            return json_message_response('Mes inválido.', 422);
        }

        $validated = $request->validate([
            'paid' => 'required|boolean',
            'amount' => 'nullable|numeric|min:0|max:99999.99',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $amount = $validated['amount'] ?? $tariff;
        $paid = (bool) $validated['paid'];

        $record = SubscriptionPaymentRecord::updateOrCreate(
            $owner + ['year' => $year, 'month' => $month],
            [
                'amount' => $amount,
                'paid' => $paid,
                'paid_at' => $validated['paid_at'] ?? ($paid ? now()->toDateString() : null),
                'notes' => $validated['notes'] ?? null,
                'updated_by' => $request->user()?->id,
            ]
        );

        return json_custom_response(['data' => [
            'user_id' => $record->user_id,
            'external_client_id' => $record->external_client_id,
            'year' => $record->year,
            'month' => $record->month,
            'paid' => $record->paid,
            'amount' => (float) $record->amount,
            'paid_at' => $record->paid_at?->toDateString(),
            'notes' => $record->notes,
        ]]);
    }

    // ═══ CLIENTES EXTERNOS (sin cuenta en la app) ════════════════════
    public function storeExternal(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'monthly_fee' => 'nullable|numeric|min:0|max:99999.99',
            'notes' => 'nullable|string|max:255',
        ]);

        $client = SubscriptionPaymentClient::create([
            'name' => trim($validated['name']),
            'email' => $validated['email'] ?? null,
            'monthly_fee' => $validated['monthly_fee'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return json_custom_response(['data' => $this->externalPayload($client)], 201);
    }

    public function updateExternal(Request $request, $clientId)
    {
        $client = SubscriptionPaymentClient::find($clientId);
        if (!$client) {
            return json_message_response('Cliente no encontrado.', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'monthly_fee' => 'sometimes|required|numeric|min:0|max:99999.99',
            'notes' => 'nullable|string|max:255',
        ]);

        $client->fill($validated)->save();

        return json_custom_response(['data' => $this->externalPayload($client)]);
    }

    public function destroyExternal($clientId)
    {
        $client = SubscriptionPaymentClient::find($clientId);
        if (!$client) {
            return json_message_response('Cliente no encontrado.', 404);
        }

        // Los meses se borran en cascada (FK external_client_id).
        $client->delete();

        return json_custom_response(['data' => ['id' => (int) $clientId]]);
    }

    // ═══ DUPLICADOS: externo ↔ usuario real ══════════════════════════
    // Para cada cliente externo propone los usuarios de la app cuyo nombre
    // se parece (tolera tildes, "Hamza"/"Hamsa", "Toni"/"Antonio",
    // apellidos de más o de menos). El admin confirma antes de fusionar.
    public function mergeCandidates()
    {
        $users = User::where('user_type', 'user')
            ->get(['id', 'first_name', 'last_name', 'display_name', 'email', 'is_personal_client', 'monthly_fee']);

        $data = SubscriptionPaymentClient::orderBy('name')->get()->map(function ($client) use ($users) {
            $candidates = $users
                ->map(function ($user) use ($client) {
                    $name = $user->display_name ?: trim("{$user->first_name} {$user->last_name}");
                    $full = trim("{$user->first_name} {$user->last_name} {$user->display_name}");
                    $emailMatch = $client->email && $user->email && strcasecmp($client->email, $user->email) === 0;
                    $score = $emailMatch ? 10 : self::nameScore($client->name, $full);

                    return [
                        'id' => $user->id,
                        'name' => $name,
                        'email' => $user->email,
                        'is_personal_client' => (bool) $user->is_personal_client,
                        'score' => $score,
                    ];
                })
                ->filter(fn ($c) => $c['score'] > 0)
                ->sortByDesc('score')
                ->take(5)
                ->values();

            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'monthly_fee' => (float) $client->monthly_fee,
                'payments_count' => $client->records()->count(),
                'candidates' => $candidates,
            ];
        });

        return json_custom_response(['data' => $data->values()]);
    }

    // Fusiona un cliente externo en un usuario real: sus meses pasan al
    // usuario (si el usuario ya tiene ese mes pagado, se respeta el del
    // usuario), el usuario queda en el seguimiento de pagos y el externo
    // se elimina.
    public function mergeExternal(Request $request, $clientId)
    {
        $validated = $request->validate(['user_id' => 'required|integer']);

        $client = SubscriptionPaymentClient::find($clientId);
        $user = User::find($validated['user_id']);
        if (!$client || !$user) {
            return json_message_response('Cliente no encontrado.', 404);
        }

        $moved = 0;
        $skipped = 0;
        DB::transaction(function () use ($client, $user, &$moved, &$skipped) {
            foreach ($client->records()->get() as $record) {
                $existing = SubscriptionPaymentRecord::where('user_id', $user->id)
                    ->where('year', $record->year)
                    ->where('month', $record->month)
                    ->first();

                if ($existing && ($existing->paid || !$record->paid)) {
                    $record->delete();
                    $skipped++;
                    continue;
                }
                $existing?->delete();
                $record->forceFill(['user_id' => $user->id, 'external_client_id' => null])->save();
                $moved++;
            }

            $user->is_personal_client = true;
            if (!$user->monthly_fee && $client->monthly_fee) {
                $user->monthly_fee = $client->monthly_fee;
            }
            $user->save();

            $client->delete();
        });

        return json_custom_response(['data' => [
            'user_id' => $user->id,
            'moved' => $moved,
            'skipped' => $skipped,
        ]]);
    }

    private static function nameTokens(string $name): array
    {
        $clean = Str::lower(Str::ascii($name));
        $tokens = preg_split('/[^a-z]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($tokens, fn ($t) => strlen($t) >= 3 && !in_array($t, ['team', 'del', 'las', 'los'], true)));
    }

    private static function tokensMatch(string $a, string $b): bool
    {
        if ($a === $b) return true;
        if (strlen($a) >= 4 && strlen($b) >= 4) {
            if (levenshtein($a, $b) <= 1) return true;                 // hamza ~ hamsa
            if (str_contains($a, $b) || str_contains($b, $a)) return true; // toni ~ antonio
        }

        return false;
    }

    public static function nameScore(string $external, string $user): int
    {
        $userTokens = self::nameTokens($user);
        $score = 0;
        foreach (self::nameTokens($external) as $i => $token) {
            foreach ($userTokens as $candidate) {
                if (self::tokensMatch($token, $candidate)) {
                    // El nombre de pila pesa más que un apellido suelto.
                    $score += $i === 0 ? 2 : 1;
                    break;
                }
            }
        }

        return $score;
    }

    private function externalPayload(SubscriptionPaymentClient $client): array
    {
        return [
            'id' => $client->id,
            'source' => 'external',
            'name' => $client->name,
            'email' => $client->email,
            'status' => 'external',
            'monthly_fee' => (float) $client->monthly_fee,
            'notes' => $client->notes,
        ];
    }

    // ═══ RESUMEN / GRÁFICAS (ingresos mensuales, total, media) ═══════
    public function summary(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        $activeClients = User::where('is_personal_client', true)->count()
            + SubscriptionPaymentClient::count();

        $paidRecords = SubscriptionPaymentRecord::where('year', $year)->where('paid', true)->get();
        $totalsByMonth = $paidRecords->groupBy('month');

        $monthly = [];
        $totalYear = 0.0;
        $monthsWithData = 0;
        foreach (range(1, 12) as $m) {
            $sum = (float) $totalsByMonth->get($m, collect())->sum('amount');
            $paidCount = $totalsByMonth->get($m, collect())->count();
            if ($sum > 0) $monthsWithData++;
            $totalYear += $sum;
            $monthly[] = [
                'month' => $m,
                'label' => self::MONTH_LABELS[$m],
                'total' => round($sum, 2),
                'paid_count' => $paidCount,
                'unpaid_count' => max($activeClients - $paidCount, 0),
            ];
        }

        $averageMonth = $monthsWithData > 0 ? $totalYear / $monthsWithData : 0;
        $averageClient = $activeClients > 0 ? $totalYear / $activeClients : 0;

        return json_custom_response([
            'data' => [
                'year' => $year,
                'monthly' => $monthly,
                'total_year' => round($totalYear, 2),
                'average_month' => round($averageMonth, 2),
                'average_client' => round($averageClient, 2),
                'total_clients' => $activeClients,
            ],
        ]);
    }
}
