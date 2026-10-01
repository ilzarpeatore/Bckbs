<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Peticiones que hace el servidor de la web pública (webbs) en nombre de un
 * visitante. Se reconocen por la cabecera X-Web-Key (= WEB_SERVER_KEY); solo
 * entonces se confía en X-Client-IP, la IP real del visitante.
 */
class WebClient
{
    public static function isTrustedWeb(Request $request): bool
    {
        $key = (string) config('services.web.server_key');

        return $key !== '' && hash_equals($key, (string) $request->header('X-Web-Key'));
    }

    public static function ip(Request $request): string
    {
        if (self::isTrustedWeb($request)) {
            $client = trim((string) $request->header('X-Client-IP'));
            if (filter_var($client, FILTER_VALIDATE_IP)) {
                return $client;
            }
        }

        return (string) $request->ip();
    }
}
