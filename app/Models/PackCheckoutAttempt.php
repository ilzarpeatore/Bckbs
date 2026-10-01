<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alguien pulsó "Comprar" en una landing y se creó una sesión de Stripe.
 * completed = pagó; expired = la sesión caducó sin pago (cesta abandonada).
 */
class PackCheckoutAttempt extends Model
{
    public const STATUS_STARTED = 'started';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_RECOVERED = 'recovered';

    protected $fillable = [
        'plan_id', 'stripe_session_id', 'email', 'status', 'amount_cents', 'recovery_consent', 'recovery_url',
        'recovery_email_sent_at', 'completed_at', 'expired_at',
        'visitor_hash', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'click_id_type', 'referrer_host', 'landing_path',
    ];

    protected $casts = [
        'recovery_consent' => 'boolean',
        'amount_cents' => 'integer',
        'recovery_email_sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }
}
