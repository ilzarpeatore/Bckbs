<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una página vista en la web pública. Analítica propia sin cookies: no hay
 * IP ni datos personales, solo un hash diario anónimo del visitante que
 * calcula la web. Ver docs/MARKETING_WEB.md.
 */
class WebPageview extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'path', 'device', 'browser',
        'visitor_hash', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'click_id_type', 'referrer_host', 'landing_path',
    ];
}
