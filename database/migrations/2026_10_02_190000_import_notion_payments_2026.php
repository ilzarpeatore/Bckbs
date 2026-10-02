<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Carga las mensualidades de 2026 ya cobradas, copiadas de la base de Notion
// "💸 Be Stronger 2026" el 2026-10-02 (mismos datos que
// bstronger-admin/src/api/subscription-payments/notion-payments-2026.ts).
// Cada nombre se asigna al cliente personal de la app con el mismo nombre
// (sin tildes ni mayúsculas); si no hay exactamente uno, se usa o se crea un
// cliente externo con ese nombre. Repetirla no duplica nada: cada mes se
// escribe con updateOrInsert sobre su clave única.
return new class extends Migration
{
    private const YEAR = 2026;

    private const PAYMENTS = [
        ['name' => 'Alberto Martín Girón', 'fee' => 65, 'months' => [2 => 65, 4 => 65, 6 => 75]],
        ['name' => 'Andres Rodriguez', 'fee' => 65, 'months' => [2 => 65, 4 => 65, 5 => 65]],
        ['name' => 'Borja Betanzos', 'fee' => 55, 'months' => [1 => 23, 2 => 55, 3 => 55, 4 => 55, 5 => 55, 6 => 55]],
        ['name' => 'Fran Garcia Romero', 'fee' => 65, 'months' => [3 => 65, 4 => 65, 5 => 65]],
        ['name' => 'Hamza Bilbao', 'fee' => 70, 'months' => [4 => 70, 5 => 70, 6 => 70]],
        ['name' => 'Mario Garcia Guiraro', 'fee' => 75, 'months' => [1 => 75, 2 => 75, 4 => 75, 5 => 75, 6 => 75]],
        ['name' => 'María Ángeles Solano', 'fee' => 60, 'months' => [1 => 60, 2 => 60, 4 => 60]],
        ['name' => 'Miguel Borrego', 'fee' => 55, 'months' => []],
        ['name' => 'Nerea TEAM', 'fee' => 70, 'months' => [2 => 35, 3 => 70, 4 => 70, 5 => 70]],
        ['name' => 'Toni Perez', 'fee' => 50, 'months' => [2 => 50, 3 => 50, 4 => 50, 5 => 50]],
        ['name' => 'Usama El Moukhlofi', 'fee' => 65, 'months' => []],
    ];

    private function normalize(?string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii((string) $name))));
    }

    public function up(): void
    {
        $users = DB::table('users')->where('is_personal_client', true)
            ->get(['id', 'first_name', 'last_name', 'display_name', 'monthly_fee']);

        DB::transaction(function () use ($users) {
            foreach (self::PAYMENTS as $row) {
                $target = $this->normalize($row['name']);
                $matches = $users->filter(fn ($u) => $this->normalize($u->first_name.' '.$u->last_name) === $target
                    || $this->normalize($u->display_name) === $target);

                if ($matches->count() === 1) {
                    $user = $matches->first();
                    $owner = ['user_id' => $user->id];
                    if ($user->monthly_fee === null) {
                        DB::table('users')->where('id', $user->id)->update(['monthly_fee' => $row['fee']]);
                    }
                } else {
                    $clientId = DB::table('subscription_payment_clients')->where('name', $row['name'])->value('id')
                        ?? DB::table('subscription_payment_clients')->insertGetId([
                            'name' => $row['name'],
                            'monthly_fee' => $row['fee'],
                            'notes' => 'Importado de Notion',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    $owner = ['external_client_id' => $clientId];
                }

                foreach ($row['months'] as $month => $amount) {
                    $key = $owner + ['year' => self::YEAR, 'month' => $month];
                    $exists = DB::table('subscription_payment_records')->where($key)->exists();
                    DB::table('subscription_payment_records')->updateOrInsert($key, [
                        'amount' => $amount,
                        'paid' => true,
                        'paid_at' => sprintf('%d-%02d-01', self::YEAR, $month),
                        'updated_at' => now(),
                    ] + ($exists ? [] : ['created_at' => now()]));
                }
            }
        });
    }

    // Los datos cargados se quedan: deshacerla borraría pagos reales que
    // pueden haberse editado después desde el panel.
    public function down(): void
    {
    }
};
