<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\WebPageview;
use App\Support\Attribution;
use App\Support\WebClient;
use Illuminate\Http\Request;

/**
 * Analítica propia de la web, sin cookies: el servidor de la web (webbs)
 * manda una visita por página vista, ya con el hash diario anónimo del
 * visitante. Solo se aceptan con la clave WEB_SERVER_KEY. Ver docs/MARKETING_WEB.md.
 */
class TrackController extends Controller
{
    public function pageview(Request $request)
    {
        if (!WebClient::isTrustedWeb($request)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $request->validate([
            'path' => 'required|string|max:255',
            'device' => 'nullable|in:mobile,tablet,desktop',
            'browser' => 'nullable|string|max:30',
        ]);

        $path = Attribution::path($request->path);
        if (!$path) {
            return response()->json(['ok' => false], 422);
        }

        WebPageview::create(array_merge(Attribution::fromRequest($request, false), [
            'path' => $path,
            'device' => $request->device,
            'browser' => $request->browser ? substr(preg_replace('/[^A-Za-z ]/', '', $request->browser), 0, 30) : null,
        ]));

        return response()->json(['ok' => true]);
    }
}
