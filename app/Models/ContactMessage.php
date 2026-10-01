<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Mensaje del formulario de contacto de la web. Ver docs/MARKETING_WEB.md. */
class ContactMessage extends Model
{
    protected $fillable = [
        'name', 'email', 'subject', 'message', 'status', 'read_at',
        'visitor_hash', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'click_id_type', 'referrer_host', 'landing_path',
    ];

    protected $casts = ['read_at' => 'datetime'];
}
