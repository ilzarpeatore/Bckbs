<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPaymentRecord;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Seguimiento manual de pagos mensuales (Informes > Seguimiento de pagos).
 * Pensado para clientes de entrenamiento personal (is_personal_client=true)
 * que pagan fuera de la pasarela de la app (transferencia, efectivo, etc.):
 * el coach marca a mano, mes a mes, quién ha pagado y por cuánto. No tiene
 * relación con Plan/PlanSubscription (esos ya se cobran y registran solos).
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

        $clientIds = $clients->pluck('id');

        $records = SubscriptionPaymentRecord::whereIn('user_id', $clientIds)
            ->where('year', $year)
            ->get()
            ->groupBy('user_id');

        $data = $clients->map(function ($client) use ($records, $year) {
            $byMonth = $records->get($client->id, collect())->keyBy('month');
            $tariff = $client->monthly_fee !== null ? (float) $client->monthly_fee : 0.0;

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

            return [
                'id' => $client->id,
                'name' => $client->display_name ?: trim("{$client->first_name} {$client->last_name}"),
                'email' => $client->email,
                'status' => $client->status,
                'monthly_fee' => $tariff,
                'months' => $months,
            ];
        });

        return json_custom_response([
            'data' => [
                'year' => $year,
                'month_labels' => self::MONTH_LABELS,
                'clients' => $data->values(),
            ],
        ]);
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

        $user = User::find($userId);
        if (!$user) {
            return json_message_response('Cliente no encontrado.', 404);
        }

        $amount = $validated['amount'] ?? (float) ($user->monthly_fee ?? 0);
        $paid = (bool) $validated['paid'];

        $record = SubscriptionPaymentRecord::updateOrCreate(
            ['user_id' => $user->id, 'year' => $year, 'month' => $month],
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
            'year' => $record->year,
            'month' => $record->month,
            'paid' => $record->paid,
            'amount' => (float) $record->amount,
            'paid_at' => $record->paid_at?->toDateString(),
            'notes' => $record->notes,
        ]]);
    }

    // ═══ RESUMEN / GRÁFICAS (ingresos mensuales, total, media) ═══════
    public function summary(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        $activeClients = User::where('is_personal_client', true)->count();

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
