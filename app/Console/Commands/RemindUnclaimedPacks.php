<?php

namespace App\Console\Commands;

use App\Mail\PackReminderMail;
use App\Models\PackPurchase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Recuerda por email a quien pagó un pack en la web y aún no lo tiene en la
 * app (status 'paid': ni se registró con ese email ni canjeó el código).
 * Dos avisos como mucho: a los 3 días de la compra y a los 10. Corre a diario
 * (app/Console/Kernel.php). Ver docs/PACKS_WEB.md.
 */
class RemindUnclaimedPacks extends Command
{
    /** Días desde la compra en que se envía cada recordatorio (1º, 2º). */
    public const REMINDER_DAYS = [3, 10];

    protected $signature = 'packs:remind-unclaimed {--dry-run : Solo lista a quién se enviaría}';

    protected $description = 'Recuerda a los compradores de packs que aún no se han registrado en la app';

    public function handle(): int
    {
        $sent = 0;

        foreach (self::REMINDER_DAYS as $index => $days) {
            $purchases = PackPurchase::with('plan')
                ->where('status', PackPurchase::STATUS_PAID)
                ->where('reminders_sent', $index)
                ->where('created_at', '<=', now()->subDays($days))
                // Nunca dos avisos seguidos (p. ej. si el cron estuvo parado).
                ->where(fn ($q) => $q->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<=', now()->subDays(3)))
                ->get();

            foreach ($purchases as $purchase) {
                if ($this->option('dry-run')) {
                    $this->line("[dry-run] {$purchase->email} — {$purchase->plan?->name} (aviso " . ($index + 1) . ')');
                    continue;
                }

                try {
                    Mail::to($purchase->email)->send(new PackReminderMail($purchase));
                    $purchase->update(['reminders_sent' => $index + 1, 'last_reminder_at' => now()]);
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning("Packs: no se pudo enviar el recordatorio de la compra {$purchase->id}", ['error' => $e->getMessage()]);
                }
            }
        }

        $this->info("Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }
}
