<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Traducción de contenido de receta de FatSecret (2026-09-21, permiso
 * explícito obtenido de FatSecret el 2026-09-20 -- ver
 * docs/FATSECRET_INTEGRATION.md sección 10). Usado SOLO desde
 * FatSecretRecipeService::getOrRefresh(), nunca en la búsqueda en vivo
 * (recipes.search) -- decisión de coste/latencia, ver el mismo doc.
 */
class DeepLTranslationService
{
    public function __construct(private readonly ?string $apiKey)
    {
    }

    private function endpoint(): string
    {
        // Una key Free termina siempre en ":fx"; una key Pro no lleva ese
        // sufijo. Usar el host equivocado da 403 con cualquier key válida
        // del otro tipo.
        $isFree = $this->apiKey !== null && str_ends_with($this->apiKey, ':fx');

        return $isFree
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';
    }

    /**
     * Traduce varios textos en UNA sola llamada (DeepL admite varios
     * parámetros `text` en el mismo request, devueltos en el mismo orden) --
     * evita una llamada por cada paso/ingrediente de la receta. Si la
     * traducción falla por lo que sea (sin key, cuota agotada, red...) NUNCA
     * debe romper el flujo de FatSecret: se devuelven los textos originales
     * tal cual, sin lanzar excepción.
     *
     * @param  string[]  $texts
     * @return string[]  mismo orden y longitud que $texts
     */
    public function translateMany(array $texts, string $targetLang = 'ES'): array
    {
        if (empty($texts)) {
            return $texts;
        }

        if (!$this->apiKey) {
            Log::warning('DeepLTranslationService: sin DEEPL_API_KEY configurada, se sirve el texto en inglés sin traducir.');
            return $texts;
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['Authorization' => "DeepL-Auth-Key {$this->apiKey}"])
                ->post($this->endpoint(), [
                    'text' => $texts,
                    'source_lang' => 'EN',
                    'target_lang' => $targetLang,
                ]);

            if (!$response->successful()) {
                Log::warning('DeepLTranslationService: fallo en la traducción, se sirve el texto en inglés sin traducir.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $texts;
            }

            $translations = $response->json('translations', []);
            if (count($translations) !== count($texts)) {
                Log::warning('DeepLTranslationService: la respuesta no trae el mismo número de traducciones que textos enviados, se sirve el texto en inglés sin traducir.');
                return $texts;
            }

            return array_map(fn (array $t) => $t['text'] ?? '', $translations);
        } catch (\Throwable $e) {
            Log::warning("DeepLTranslationService: excepción al traducir: {$e->getMessage()}");
            return $texts;
        }
    }
}
