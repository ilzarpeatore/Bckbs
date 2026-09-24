<?php

namespace App\Services\ExerciseMatcher;

use App\Models\Exercise;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve con IA qué ejercicio del catálogo equivale a un nombre de una fuente externa.
 *
 * El matcher por reglas (ExerciseMatcher) no entiende traducciones ni anglicismos de gimnasio
 * ("leg curl" = "curl de piernas", "pec deck" = "peck deck", "high row" = "remo alto"), así que
 * crea un ejercicio nuevo aunque el catálogo ya lo tenga. Aquí se le da al modelo el catálogo
 * completo (id + título) y la lista de nombres sin resolver, en UNA llamada por import, y solo
 * puede devolver ids que existen (o null si no hay equivalente exacto) -- nunca inventa nombres.
 *
 * Sin ANTHROPIC_API_KEY, o si la llamada falla, devuelve [] y el importador sigue con su
 * comportamiento anterior (nunca rompe un import).
 */
class ExerciseEquivalenceResolver
{
    private const CACHE_PREFIX = 'exercise_equivalence_v1:';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model = 'claude-haiku-4-5-20251001',
    ) {
    }

    public function enabled(): bool
    {
        return (bool) $this->apiKey;
    }

    /**
     * @param  string[]  $names  nombres de la fuente sin match del matcher por reglas
     * @return array<string, array{exercise_id:int, reason:string}>  solo los que tienen equivalente; clave = nombre original
     */
    public function resolve(array $names): array
    {
        $names = array_values(array_unique(array_filter(array_map('trim', $names), fn ($n) => $n !== '')));
        if ($names === [] || !$this->enabled()) {
            return [];
        }

        $catalog = Exercise::query()->where('status', 'active')->orderBy('id')->pluck('title', 'id')->all();
        if ($catalog === []) {
            return [];
        }

        $result = [];
        $pending = [];
        foreach ($names as $name) {
            $hit = Cache::get(self::CACHE_PREFIX . sha1(mb_strtolower($name)));
            if ($hit === null) {
                $pending[] = $name;
            } elseif (is_array($hit) && isset($catalog[$hit['exercise_id'] ?? 0])) {
                $result[$name] = $hit;
            } // $hit === false: ya se preguntó y no hay equivalente (se cachea por poco tiempo abajo)
        }

        foreach (array_chunk($pending, 40) as $chunk) {
            $answers = $this->ask($catalog, $chunk);
            foreach ($chunk as $name) {
                $answer = $answers[$name] ?? null;
                $key = self::CACHE_PREFIX . sha1(mb_strtolower($name));
                if ($answer !== null && isset($catalog[$answer['exercise_id']])) {
                    $result[$name] = $answer;
                    Cache::forever($key, $answer);
                } elseif ($answers !== null) {
                    Cache::put($key, false, now()->addDays(3)); // sin equivalente: no repetir la pregunta ya
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<int,string>  $catalog  id => título
     * @param  string[]  $names
     * @return array<string, array{exercise_id:int, reason:string}>|null  null si la llamada falló
     */
    private function ask(array $catalog, array $names): ?array
    {
        $list = '';
        foreach ($catalog as $id => $title) {
            $list .= $id . "\t" . $title . "\n";
        }

        $system = [
            [
                'type' => 'text',
                'text' => "Eres un experto en entrenamiento de fuerza que sabe traducir nombres de ejercicios entre inglés y español, "
                    . "incluida la jerga y los anglicismos de gimnasio (leg curl = curl de piernas, pec deck = aperturas en máquina, "
                    . "lat pulldown = jalón al pecho, high row = remo alto, skull crusher = press francés, hip thrust = empuje de cadera...).\n\n"
                    . "Te doy el CATÁLOGO de ejercicios de la app (id, tabulador, título en español) y una lista de NOMBRES de ejercicios "
                    . "de un programa externo. Para cada nombre, di qué ejercicio del catálogo ES ese mismo ejercicio.\n\n"
                    . "Reglas:\n"
                    . "- Solo vale el MISMO ejercicio: mismo movimiento, mismo tipo de equipo (máquina, polea, barra, mancuernas, peso corporal...) "
                    . "y misma lateralidad (unilateral/bilateral) y posición (sentado, tumbado, inclinado...). Una variante distinta NO es equivalente.\n"
                    . "- El catálogo viene de una traducción automática: 'con palanca' o 'peck deck' pueden ser la máquina que el programa llama "
                    . "'máquina'. Ten eso en cuenta, pero no fuerces un parecido.\n"
                    . "- Si no hay equivalente claro, devuelve null. Es mejor null que un ejercicio incorrecto.\n"
                    . "- Solo puedes devolver ids que aparezcan en el catálogo.\n\n"
                    . "Responde ÚNICAMENTE con un array JSON, un objeto por nombre y en el mismo orden: "
                    . "[{\"name\": \"...\", \"exercise_id\": 123 o null, \"reason\": \"máx. 12 palabras\"}]",
            ],
            [
                'type' => 'text',
                'text' => "CATÁLOGO (id<TAB>título):\n" . $list,
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => 4096,
                    'temperature' => 0,
                    'system' => $system,
                    'messages' => [[
                        'role' => 'user',
                        'content' => "NOMBRES:\n" . json_encode(array_values($names), JSON_UNESCAPED_UNICODE),
                    ]],
                ]);

            if (!$response->successful()) {
                Log::warning('ExerciseEquivalenceResolver: fallo de la API', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

                return null;
            }

            $text = (string) data_get($response->json(), 'content.0.text', '');
            $start = strpos($text, '[');
            $end = strrpos($text, ']');
            if ($start === false || $end === false) {
                return null;
            }
            $rows = json_decode(substr($text, $start, $end - $start + 1), true);
            if (!is_array($rows)) {
                return null;
            }

            $out = [];
            foreach ($rows as $i => $row) {
                $name = $names[$i] ?? null;
                // se ancla por posición y se comprueba el nombre devuelto para no desalinear respuestas
                if ($name === null || !is_array($row) || (isset($row['name']) && mb_strtolower(trim((string) $row['name'])) !== mb_strtolower($name))) {
                    $name = is_array($row) ? $this->findName($names, (string) ($row['name'] ?? '')) : null;
                }
                if ($name === null || !is_array($row) || !isset($row['exercise_id']) || !is_numeric($row['exercise_id'])) {
                    continue;
                }
                $out[$name] = ['exercise_id' => (int) $row['exercise_id'], 'reason' => mb_substr((string) ($row['reason'] ?? ''), 0, 160)];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('ExerciseEquivalenceResolver: excepción: ' . $e->getMessage());

            return null;
        }
    }

    private function findName(array $names, string $candidate): ?string
    {
        foreach ($names as $n) {
            if (mb_strtolower(trim($n)) === mb_strtolower(trim($candidate))) {
                return $n;
            }
        }

        return null;
    }
}
