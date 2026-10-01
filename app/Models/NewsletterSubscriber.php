<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Alta en la newsletter / lista de espera de la web (doble opt-in). Ver docs/MARKETING_WEB.md. */
class NewsletterSubscriber extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $fillable = [
        'email', 'source', 'status', 'confirm_token', 'unsubscribe_token',
        'confirmation_sent_at', 'confirmed_at', 'unsubscribed_at',
        'visitor_hash', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'click_id_type', 'referrer_host', 'landing_path',
    ];

    protected $casts = [
        'confirmation_sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    protected $hidden = ['confirm_token', 'unsubscribe_token'];

    public static function newToken(): string
    {
        return Str::random(48);
    }
}
