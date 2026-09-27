<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Técnicas especiales de intensidad (pedido 2026-09-27): se guardan en el
 * `prescribed` de cada ejercicio de cada semana, junto a series/reps/RIR:
 *
 *   tecnica        => slug del catálogo (abajo) u "otra"
 *   tecnica_series => "todas" (por defecto) | "ultima" (solo la última serie)
 *   tecnica_otra   => texto libre, solo con tecnica = "otra"
 *
 * Única fuente del catálogo: el panel y la app lo leen de
 * GET admin/training-technique-list y GET v1/training-technique-list
 * (TrainingTechniqueController), no tienen copia propia.
 */
class TrainingTechniques
{
    public const OTHER = 'otra';
    public const SERIES_ALL = 'todas';
    public const SERIES_LAST = 'ultima';

    public const KEYS = ['tecnica', 'tecnica_series', 'tecnica_otra'];

    /**
     * slug => ficha para el cliente (la app la abre al pulsar la técnica):
     *   label       nombre
     *   description qué es, en una o dos frases
     *   steps       paso a paso
     *   mistakes    errores comunes / seguridad
     *   logging     cómo apuntar esa serie en la app
     * Primer borrador redactado por Claude (2026-09-27), pendiente de revisión del coach.
     */
    public const CATALOG = [
        'cluster_sets' => [
            'label'       => 'Cluster sets',
            'description' => 'Divides la serie en mini-bloques con pausas muy cortas para hacer más repeticiones de calidad con el mismo peso.',
            'steps'       => [
                'Haz repeticiones hasta que te queden 1-2 en recámara (RIR 1).',
                'Deja el peso apoyado o en posición segura y descansa 10-15 segundos.',
                'Haz 1-2 repeticiones más con buena técnica.',
                'Repite la pausa de 10-15 s y las 1-2 repeticiones hasta completar 3 mini-bloques.',
            ],
            'mistakes'    => [
                'Alargar las pausas: más de 15-20 s ya es otra serie.',
                'Llegar al fallo en cada mini-bloque; la idea es mantener la técnica limpia.',
                'Usarlo en ejercicios donde no puedes descansar con seguridad con el peso encima.',
            ],
            'logging'     => 'Apunta en esa serie el total de repeticiones (las de la primera parte más las de los mini-bloques) y el peso usado.',
        ],
        'bisets' => [
            'label'       => 'Bisets',
            'description' => 'Dos ejercicios seguidos, sin descanso entre ellos. Descansas solo al terminar el par.',
            'steps'       => [
                'Prepara los dos ejercicios antes de empezar (pesos, máquina, banco).',
                'Haz la serie del primer ejercicio.',
                'Pasa directamente al segundo ejercicio, sin descansar.',
                'Al terminar el segundo, descansa unos 90 segundos y repite el par.',
            ],
            'mistakes'    => [
                'Descansar entre los dos ejercicios: el descanso va solo al final del par.',
                'No tener preparado el segundo ejercicio y perder tiempo buscando material.',
            ],
            'logging'     => 'Apunta cada ejercicio en su propia tarjeta, con sus repeticiones y peso, como una serie normal.',
        ],
        'superseries' => [
            'label'       => 'Superseries',
            'description' => 'Dos ejercicios de músculos distintos (por ejemplo, empuje y tirón) seguidos, sin descanso entre ellos.',
            'steps'       => [
                'Prepara los dos ejercicios antes de empezar.',
                'Haz la serie del primer ejercicio.',
                'Pasa directamente al segundo, sin descansar.',
                'Descansa al terminar el par y repite.',
            ],
            'mistakes'    => [
                'Descansar entre los dos ejercicios.',
                'Bajar la calidad del segundo ejercicio por ir con prisa: mantén el mismo control.',
            ],
            'logging'     => 'Apunta cada ejercicio en su propia tarjeta, como una serie normal.',
        ],
        'rest_pause' => [
            'label'       => 'Rest-pause',
            'description' => 'Tras llegar al fallo haces una pausa corta y sigues con el mismo peso para sacar unas repeticiones extra.',
            'steps'       => [
                'Haz la serie hasta el fallo técnico: no puedes hacer otra repetición con buena forma.',
                'Deja el peso en posición segura y respira 15-20 segundos.',
                'Con el mismo peso, vuelve a hacer repeticiones hasta el fallo.',
                'Termina ahí la serie.',
            ],
            'mistakes'    => [
                'Perder la técnica para arañar una repetición más.',
                'Usarlo en ejercicios con riesgo si fallas (sentadilla o press banca con barra libre sin ayuda).',
                'Descansar más de 20 s: se convierte en otra serie.',
            ],
            'logging'     => 'Apunta en esa serie el total de repeticiones (antes y después de la pausa) y el peso usado.',
        ],
        'rest_pause_ampliado' => [
            'label'       => 'Rest-pause ampliado',
            'description' => 'Como el rest-pause, pero con tres mini-series hasta el fallo separadas por pausas cortas.',
            'steps'       => [
                'Haz la serie hasta el fallo técnico.',
                'Descansa 15 segundos y haz repeticiones hasta el fallo con el mismo peso.',
                'Descansa otros 15 segundos y repite una última vez hasta el fallo.',
                'Termina la serie tras la tercera mini-serie.',
            ],
            'mistakes'    => [
                'Alargar las pausas entre mini-series.',
                'Perder la técnica en la última mini-serie.',
                'Hacerlo en ejercicios donde fallar es peligroso sin ayuda.',
            ],
            'logging'     => 'Apunta en esa serie el total de repeticiones de las tres mini-series y el peso.',
        ],
        'drop_sets' => [
            'label'       => 'Drop sets',
            'description' => 'Al llegar al fallo bajas el peso y sigues sin descansar, para agotar el músculo.',
            'steps'       => [
                'Haz la serie hasta el fallo técnico.',
                'Baja el peso un 20-25 % lo más rápido posible (cambia mancuernas, pin o discos).',
                'Sin descansar, haz repeticiones hasta el fallo con el peso nuevo.',
                'Termina ahí la serie, salvo que tu entrenador indique más bajadas.',
            ],
            'mistakes'    => [
                'Tardar mucho en cambiar el peso: prepáralo antes de empezar.',
                'Bajar demasiado poco peso y no poder hacer casi repeticiones.',
                'Hacerlo con barra libre sin ayuda en ejercicios de riesgo.',
            ],
            'logging'     => 'Apunta el peso inicial y el total de repeticiones de la serie; si quieres, detalla la bajada en la nota para tu entrenador.',
        ],
        'drop_sets_mecanicos' => [
            'label'       => 'Drop sets mecánicos',
            'description' => 'Al llegar al fallo no bajas el peso: cambias a una variante más fácil del mismo ejercicio y sigues.',
            'steps'       => [
                'Haz la serie hasta el fallo técnico con la variante más difícil (por ejemplo, press inclinado).',
                'Sin soltar el peso ni descansar, cambia a una variante más favorable (por ejemplo, press plano).',
                'Haz repeticiones hasta el fallo con esa variante.',
                'Termina ahí la serie.',
            ],
            'mistakes'    => [
                'Descansar al cambiar de variante.',
                'Elegir una variante que no es realmente más fácil.',
            ],
            'logging'     => 'Apunta el peso y el total de repeticiones de las dos variantes en esa serie.',
        ],
        'series_mecanicas' => [
            'label'       => 'Series mecánicas',
            'description' => 'Enlazas dos ejercicios del mismo patrón: el primero cerca del fallo y el segundo, más fácil, hasta el fallo.',
            'steps'       => [
                'Haz el ejercicio A hasta que te quede 1 repetición en recámara (RIR 1).',
                'Sin descansar, pasa al ejercicio B, del mismo patrón pero más fácil.',
                'Haz repeticiones del ejercicio B hasta el fallo.',
                'Descansa y repite si hay más series.',
            ],
            'mistakes'    => [
                'Llegar al fallo en el ejercicio A: debe quedar 1 repetición.',
                'Descansar al cambiar de ejercicio.',
            ],
            'logging'     => 'Apunta cada ejercicio en su tarjeta, con sus repeticiones y peso.',
        ],
        'bfr' => [
            'label'       => 'BFR (oclusión)',
            'description' => 'Entrenamiento con restricción parcial del flujo sanguíneo: peso ligero y muchas repeticiones con una banda en la extremidad.',
            'steps'       => [
                'Coloca la banda en la parte alta del brazo o del muslo, apretada pero sin dolor ni hormigueo (7 sobre 10 de presión).',
                'Usa un peso ligero y haz una serie de 20-30 repeticiones.',
                'Descansa unos 30 segundos sin quitar la banda.',
                'Haz 3 series más cortas (unas 15 repeticiones) a un esfuerzo de RPE 7-8.',
                'Quita la banda al terminar el ejercicio.',
            ],
            'mistakes'    => [
                'Apretar demasiado: si notas hormigueo, dolor intenso o la piel se pone morada o blanca, afloja o quita la banda.',
                'Dejar la banda puesta más de 15-20 minutos seguidos.',
                'Hacerlo si tienes problemas circulatorios, de coagulación o tensión alta sin consultarlo antes.',
            ],
            'logging'     => 'Apunta cada serie con sus repeticiones y el peso, como una serie normal.',
        ],
        'myo_reps' => [
            'label'       => 'Myo-reps',
            'description' => 'Una serie de activación cerca del fallo seguida de mini-series cortas con pausas de pocas respiraciones.',
            'steps'       => [
                'Haz una serie de activación de 12-20 repeticiones, cerca del fallo.',
                'Descansa 3-5 respiraciones profundas (unos 10-15 segundos).',
                'Haz 3-5 repeticiones con el mismo peso.',
                'Repite pausa y mini-serie hasta que no puedas completar las 3 repeticiones.',
            ],
            'mistakes'    => [
                'Descansar demasiado entre mini-series.',
                'Hacer la serie de activación demasiado lejos del fallo.',
            ],
            'logging'     => 'Apunta en esa serie el total de repeticiones (activación más mini-series) y el peso.',
        ],
        'parciales' => [
            'label'       => 'Parciales',
            'description' => 'Al llegar al fallo sigues con repeticiones de recorrido corto en el tramo donde eres más fuerte.',
            'steps'       => [
                'Haz la serie hasta el fallo con recorrido completo.',
                'Sin descansar, haz repeticiones de medio recorrido o menos en la parte más fuerte del movimiento.',
                'Continúa hasta que no puedas moverte con control.',
            ],
            'mistakes'    => [
                'Hacer parciales desde el principio: primero se llega al fallo con recorrido completo.',
                'Rebotar o usar impulso.',
            ],
            'logging'     => 'Apunta las repeticiones completas de la serie; si quieres, añade las parciales en la nota para tu entrenador.',
        ],
        'excentricas' => [
            'label'       => 'Excéntricas lentas',
            'description' => 'Bajas el peso muy despacio y controlado en cada repetición.',
            'steps'       => [
                'Sube el peso a velocidad normal.',
                'Bájalo contando 3-5 segundos, con control durante todo el recorrido.',
                'Repite así en todas las repeticiones de la serie.',
            ],
            'mistakes'    => [
                'Acelerar al final de la bajada.',
                'Usar el mismo peso de siempre: normalmente hace falta algo menos.',
            ],
            'logging'     => 'Apunta repeticiones y peso como una serie normal.',
        ],
        'isometricas' => [
            'label'       => 'Isométricas',
            'description' => 'Mantienes una posición concreta sin moverte durante el tiempo indicado.',
            'steps'       => [
                'Colócate en la posición que indica tu entrenador (por ejemplo, a mitad del recorrido).',
                'Mantenla quieto, respirando con normalidad, el tiempo marcado.',
                'Suelta el peso con control al terminar.',
            ],
            'mistakes'    => [
                'Aguantar la respiración.',
                'Ir perdiendo la posición sin darte cuenta.',
            ],
            'logging'     => 'Apunta el tiempo aguantado (o las repeticiones, si las hay) y el peso.',
        ],
        self::OTHER => [
            'label'       => 'Otra',
            'description' => 'Técnica indicada por tu entrenador. Revisa sus notas en este ejercicio y pregúntale si tienes dudas.',
            'steps'       => [],
            'mistakes'    => [],
            'logging'     => 'Apunta la serie como te haya indicado tu entrenador; si tienes dudas, déjale una nota.',
        ],
    ];

    /** @return array<int, array{key: string, label: string, description: string, steps: string[], mistakes: string[], logging: string}> */
    public static function list(): array
    {
        $out = [];
        foreach (self::CATALOG as $key => $item) {
            $out[] = ['key' => $key] + $item;
        }

        return $out;
    }

    /**
     * Slug a partir de lo que escribe el coach en el Excel: el slug, la
     * etiqueta ("Rest-pause", "drop sets") o texto libre. Texto que no
     * coincide con ninguna => [otra, texto].
     *
     * @return array{0: string, 1: string|null}|null
     */
    public static function resolve(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $norm = self::normalize($value);
        foreach (self::CATALOG as $key => ['label' => $label]) {
            if ($key === self::OTHER) {
                continue;
            }
            if ($norm === self::normalize($key) || $norm === self::normalize($label)) {
                return [$key, null];
            }
        }

        return [self::OTHER, $value];
    }

    /** "última", "ultima serie", "last" => ultima; cualquier otra cosa => todas. */
    public static function resolveSeries(?string $value): string
    {
        $norm = self::normalize((string) $value);

        return str_starts_with($norm, 'ultim') || $norm === 'last' ? self::SERIES_LAST : self::SERIES_ALL;
    }

    /**
     * Deja coherentes las claves de técnica de un `prescribed` ya fusionado:
     * sin técnica no quedan tecnica_series/tecnica_otra; con técnica,
     * tecnica_series por defecto "todas"; "otra" exige texto.
     *
     * @throws ValidationException si el slug no está en el catálogo
     */
    public static function normalizePrescribed(array $prescribed): array
    {
        $tecnica = isset($prescribed['tecnica']) ? trim((string) $prescribed['tecnica']) : '';
        if ($tecnica === '') {
            unset($prescribed['tecnica'], $prescribed['tecnica_series'], $prescribed['tecnica_otra']);

            return $prescribed;
        }
        if (!array_key_exists($tecnica, self::CATALOG)) {
            throw ValidationException::withMessages(['changes' => "Técnica desconocida: {$tecnica}."]);
        }
        $prescribed['tecnica'] = $tecnica;
        $prescribed['tecnica_series'] = ($prescribed['tecnica_series'] ?? null) === self::SERIES_LAST ? self::SERIES_LAST : self::SERIES_ALL;

        $otra = trim((string) ($prescribed['tecnica_otra'] ?? ''));
        if ($tecnica === self::OTHER) {
            if ($otra === '') {
                throw ValidationException::withMessages(['changes' => 'Con la técnica «Otra» hay que escribir cuál es.']);
            }
            $prescribed['tecnica_otra'] = mb_substr($otra, 0, 120);
        } else {
            unset($prescribed['tecnica_otra']);
        }

        return $prescribed;
    }

    private static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return preg_replace('/[^a-z0-9]+/', '', $s);
    }
}
