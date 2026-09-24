<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda de texto insensible a acentos/mayúsculas y tolerante a erratas,
 * común a TODOS los buscadores del backend. Tiene un gemelo con las mismas
 * reglas en el admin (bstronger-admin/src/lib/textSearch.ts).
 *
 * Reglas:
 *  - Se normaliza: minúsculas, sin acentos ("Elevación" == "elevacion"), ñ == n,
 *    espacios colapsados.
 *  - La consulta se parte en palabras; TODAS deben encontrarse (en cualquier
 *    columna/campo), en cualquier orden.
 *  - Una palabra encaja si está contenida en alguna palabra del texto, o si se
 *    parece a una (o a su comienzo, para búsquedas a medio escribir) con hasta
 *    N erratas de distancia Damerau-Levenshtein (sustituir/insertar/borrar/
 *    intercambiar letras): N = 0 hasta 3 letras, 1 de 4 a 7, 2 desde 8.
 *
 * En base de datos: primero el LIKE normal (rápido, con la collation
 * utf8mb4_unicode_ci que ya ignora acentos); solo si no da NINGÚN resultado
 * se recurre al filtrado tolerante a erratas en PHP sobre id + columnas.
 */
class FuzzySearch
{
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ý' => 'y', 'ÿ' => 'y',
    ];

    public static function normalize(?string $text): string
    {
        $t = mb_strtolower((string) $text, 'UTF-8');
        $t = strtr($t, self::ACCENTS);
        $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t) ?? $t;

        return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
    }

    /** @return array<int,string> */
    public static function tokens(?string $text): array
    {
        $n = self::normalize($text);

        return $n === '' ? [] : explode(' ', $n);
    }

    /** Erratas permitidas según la longitud de la palabra buscada. */
    public static function budget(int $length): int
    {
        return $length <= 3 ? 0 : ($length <= 7 ? 1 : 2);
    }

    /** Distancia Damerau-Levenshtein (versión "optimal string alignment"). */
    public static function distance(string $a, string $b): int
    {
        $la = strlen($a);
        $lb = strlen($b);
        if ($la === 0) {
            return $lb;
        }
        if ($lb === 0) {
            return $la;
        }

        $prev2 = [];
        $prev = range(0, $lb);
        for ($i = 1; $i <= $la; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $v = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $v = min($v, $prev2[$j - 2] + 1);
                }
                $cur[$j] = $v;
            }
            $prev2 = $prev;
            $prev = $cur;
        }

        return $prev[$lb];
    }

    /**
     * Distancia (0 = exacta/contenida) a la que una palabra buscada encaja con las
     * palabras de un texto ya normalizado, o null si no encaja.
     *
     * @param  array<int,string>  $words
     */
    public static function tokenDistance(string $token, array $words): ?int
    {
        $budget = self::budget(strlen($token));
        $best = null;

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            if (str_contains($word, $token)) {
                return 0;
            }
            if ($budget === 0) {
                continue;
            }
            // La palabra entera, o solo su comienzo (búsqueda a medio escribir con errata).
            $d = min(
                self::distance($token, $word),
                self::distance($token, substr($word, 0, strlen($token))),
            );
            if ($d <= $budget && ($best === null || $d < $best)) {
                $best = $d;
            }
        }

        return $best;
    }

    /**
     * Puntuación de una consulta contra varios campos: null si no encaja
     * (todas las palabras deben encajar); menor = mejor.
     *
     * @param  array<int,string|null>  $fields
     */
    public static function score(string $query, array $fields): ?int
    {
        $tokens = self::tokens($query);
        if ($tokens === []) {
            return 0;
        }

        $words = [];
        foreach ($fields as $field) {
            foreach (self::tokens($field) as $w) {
                $words[] = $w;
            }
        }

        $total = 0;
        foreach ($tokens as $token) {
            $d = self::tokenDistance($token, $words);
            if ($d === null) {
                return null;
            }
            $total += $d;
        }

        return $total;
    }

    /**
     * Aplica la búsqueda a una consulta Eloquent sobre columnas propias del modelo.
     * Sin efecto si $term está vacío. Mantiene los demás filtros del $query.
     *
     * @param  array<int,string>  $columns
     */
    /**
     * Cierre para un `where(...)`: todas las palabras deben aparecer en alguna de las columnas
     * (LIKE; la collation utf8mb4_unicode_ci ya ignora acentos y mayúsculas). Sin tolerancia a
     * erratas: para condiciones anidadas donde no se puede reordenar por relevancia.
     *
     * @param  array<int,string>  $columns
     */
    public static function likeClosure(array $columns, ?string $term): \Closure
    {
        $tokens = self::tokens($term);

        return function ($q) use ($columns, $tokens) {
            foreach ($tokens as $token) {
                $escaped = addcslashes($token, '%_\\');
                $q->where(function ($w) use ($columns, $escaped) {
                    foreach ($columns as $column) {
                        $w->orWhere($column, 'LIKE', "%{$escaped}%");
                    }
                });
            }
        };
    }

    /**
     * Ids de un modelo que encajan con la búsqueda (con tolerancia a erratas), para usarlos como
     * `whereIn` cuando la búsqueda llega a través de una relación.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<int,string>  $columns
     * @return array<int,int|string>
     */
    public static function matchingIds(string $modelClass, array $columns, ?string $term, int $limit = 500): array
    {
        if (self::tokens($term) === []) {
            return [];
        }

        $query = self::apply($modelClass::query(), $columns, $term, null, $limit);

        return $query->limit($limit)->pluck((new $modelClass())->getQualifiedKeyName())->all();
    }

    /**
     * @param  array<int,string>  $columns  columnas para el LIKE
     * @param  array<int,string>|null  $fuzzyColumns  columnas para la tolerancia a erratas (por defecto las mismas;
     *                                                pasar solo las cortas, p. ej. el título, si las otras son texto largo)
     */
    public static function apply(Builder $query, array $columns, ?string $term, ?array $fuzzyColumns = null, int $limit = 200): Builder
    {
        $tokens = self::tokens($term);
        if ($tokens === [] || $columns === []) {
            return $query;
        }

        $like = self::likeClosure($columns, $term);

        // Camino normal: LIKE por palabras.
        if ((clone $query)->where($like)->exists()) {
            return $query->where($like);
        }

        // Sin resultados: tolerancia a erratas sobre id + columnas de texto.
        $model = $query->getModel();
        $key = $model->getKeyName();
        $plain = array_map(fn ($c) => str_contains($c, '.') ? substr($c, strrpos($c, '.') + 1) : $c, $fuzzyColumns ?? $columns);

        $ranked = [];
        foreach ($model->newQuery()->select(array_merge([$key], $plain))->get() as $row) {
            $score = self::score((string) $term, array_map(fn ($c) => (string) $row->{$c}, $plain));
            if ($score !== null) {
                $ranked[$row->{$key}] = $score;
            }
        }

        if ($ranked === []) {
            return $query->whereRaw('1 = 0');
        }

        asort($ranked); // estable en PHP >= 8: a igual puntuación conserva el orden
        $ids = array_slice(array_keys($ranked), 0, $limit);

        $qualifiedKey = $model->getQualifiedKeyName();
        $query->whereIn($qualifiedKey, $ids);
        $query->orderByRaw('FIELD('.$qualifiedKey.', '.implode(',', array_map('intval', $ids)).')');

        return $query;
    }
}
