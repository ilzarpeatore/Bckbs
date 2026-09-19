<?php

namespace App\Services\FatSecret;

use App\Exceptions\FatSecretUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP de bajo nivel para la FatSecret Platform API -- OAuth 2.0
 * client credentials (no OAuth 1.0/HMAC, ver docs/FATSECRET_INTEGRATION.md
 * sección 1). Solo sabe pedir el token y hacer la llamada firmada; la
 * lógica de negocio (qué método pedir, cómo calcular per-gram, caché de
 * recetas) vive en FatSecretFoodService/FatSecretRecipeService.
 *
 * IMPORTANTE: FatSecret exige pedir el token desde una IP registrada en su
 * panel -- esto SOLO puede ejecutarse desde el VPS de producción, nunca
 * desde un runner de CI ni desde el navegador del admin/cliente.
 */
class FatSecretClient
{
    private const TOKEN_URL = 'https://oauth.fatsecret.com/connect/token';
    private const API_URL = 'https://platform.fatsecret.com/rest/server.api';
    private const TOKEN_CACHE_KEY = 'fatsecret_oauth_token';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    /**
     * Token de 24h (86400s) cacheado con margen de 60s para no usarlo justo
     * al expirar.
     */
    private function getAccessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 86340, function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'client_credentials',
                    'scope' => 'basic',
                ]);

            if (!$response->successful() || !$response->json('access_token')) {
                Log::warning('FatSecretClient: fallo al obtener token OAuth2', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new FatSecretUnavailableException('No se pudo autenticar con FatSecret.');
            }

            return $response->json('access_token');
        });
    }

    /**
     * @param  string  $method  ej. 'foods.search', 'food.get.v4', 'recipes.search.v3', 'recipe.get.v2'
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = []): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->asForm()
            ->post(self::API_URL, array_merge($params, [
                'method' => $method,
                'format' => 'json',
            ]));

        if ($response->status() === 401) {
            // El token pudo caducar justo entre el cache hit y la llamada --
            // se limpia y se reintenta una única vez con uno nuevo.
            Cache::forget(self::TOKEN_CACHE_KEY);
            $token = $this->getAccessToken();
            $response = Http::withToken($token)
                ->asForm()
                ->post(self::API_URL, array_merge($params, [
                    'method' => $method,
                    'format' => 'json',
                ]));
        }

        if (!$response->successful()) {
            Log::warning("FatSecretClient: fallo en {$method}", [
                'status' => $response->status(),
                'body' => $response->body(),
                'params' => $params,
            ]);
            throw new FatSecretUnavailableException("FatSecret no respondió correctamente a {$method}.");
        }

        $data = $response->json();

        if (isset($data['error'])) {
            Log::warning("FatSecretClient: {$method} devolvió error de API", ['error' => $data['error']]);
            throw new FatSecretUnavailableException($data['error']['message'] ?? "Error de FatSecret en {$method}.");
        }

        return $data ?? [];
    }
}
