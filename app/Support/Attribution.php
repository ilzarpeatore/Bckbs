<?php

namespace App\Support;

use App\Models\WebPageview;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Atribución de una visita o conversión: de qué campaña / web llegó. La envía
 * la web en `attribution` (UTM de la URL, identificador de clic de anuncios,
 * web de origen y el hash diario anónimo del visitante). Si la conversión no
 * trae UTM pero sí visitor_hash, hereda la primera visita de ese visitante en
 * las últimas 24 h que sí los tenía (primer contacto, sin cookies).
 */
class Attribution
{
    public const CLICK_IDS = ['gclid', 'fbclid', 'ttclid', 'msclkid', 'li_fat_id'];

    public const FIELDS = [
        'visitor_hash', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'click_id_type', 'referrer_host', 'landing_path',
    ];

    /** @return array<string, string|null> columnas listas para guardar */
    public static function fromRequest(Request $request, bool $inherit = true): array
    {
        $raw = (array) $request->input('attribution', []);

        $data = [
            'visitor_hash' => self::match($raw['visitor_hash'] ?? null, '/^[a-f0-9]{16,32}$/'),
            'utm_source' => self::clean($raw['utm_source'] ?? null, 120),
            'utm_medium' => self::clean($raw['utm_medium'] ?? null, 120),
            'utm_campaign' => self::clean($raw['utm_campaign'] ?? null, 160),
            'utm_content' => self::clean($raw['utm_content'] ?? null, 160),
            'utm_term' => self::clean($raw['utm_term'] ?? null, 160),
            'click_id_type' => in_array($raw['click_id_type'] ?? null, self::CLICK_IDS, true) ? $raw['click_id_type'] : null,
            'referrer_host' => self::match(Str::lower((string) ($raw['referrer_host'] ?? '')), '/^[a-z0-9.-]{3,190}$/'),
            'landing_path' => self::path($raw['landing_path'] ?? null),
        ];

        if ($inherit && $data['visitor_hash'] && !$data['utm_source'] && !$data['click_id_type']) {
            $first = WebPageview::where('visitor_hash', $data['visitor_hash'])
                ->where('created_at', '>=', now()->subDay())
                ->where(fn ($q) => $q->whereNotNull('utm_source')->orWhereNotNull('click_id_type')->orWhereNotNull('referrer_host'))
                ->orderBy('id')
                ->first();
            if ($first) {
                foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'click_id_type', 'referrer_host', 'landing_path'] as $f) {
                    $data[$f] = $data[$f] ?? $first->{$f};
                }
            }
        }

        return $data;
    }

    public static function path(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || $value[0] !== '/') {
            return null;
        }
        $value = strtok($value, '?#') ?: '/';

        return Str::limit($value, 250, '');
    }

    private static function clean(mixed $value, int $max): ?string
    {
        $value = is_string($value) ? trim(strip_tags($value)) : '';

        return $value === '' ? null : Str::limit(Str::lower($value), $max, '');
    }

    private static function match(mixed $value, string $pattern): ?string
    {
        return is_string($value) && preg_match($pattern, $value) ? $value : null;
    }
}
