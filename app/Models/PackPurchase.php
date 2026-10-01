<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Compra de un pack (Plan con sold_on_web) hecha en la web con Stripe, sin
 * cuenta. Se vincula a una cuenta de la app por email (automático al
 * registrarse o si ya existía) o canjeando `redeem_code`. Ver
 * App\Services\PackPurchaseService y docs/PACKS_WEB.md.
 */
class PackPurchase extends Model
{
    public const STATUS_PAID = 'paid';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'plan_id', 'email', 'customer_name', 'stripe_session_id', 'stripe_payment_intent_id',
        'amount_cents', 'currency', 'status', 'redeem_code', 'user_id', 'plan_subscription_id',
        'claimed_at', 'refunded_at', 'reminders_sent', 'last_reminder_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'claimed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'reminders_sent' => 'integer',
        'last_reminder_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PlanSubscription::class, 'plan_subscription_id');
    }

    /** Código de canje legible: 10 caracteres sin 0/O/1/I para dictarlo sin errores. */
    public static function generateRedeemCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('redeem_code', $code)->exists());

        return $code;
    }

    public static function normalizeEmail(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    public static function normalizeCode(?string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    }
}
