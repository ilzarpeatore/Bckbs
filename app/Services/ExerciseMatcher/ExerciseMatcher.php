<?php

namespace App\Services\ExerciseMatcher;

use App\Models\BodyPart;
use App\Models\Equipment;
use App\Models\Exercise;
use Illuminate\Support\Facades\Cache;

/**
 * Matcher de ejercicios de fuentes externas contra la BD.
 *
 * Para cada ejercicio fuente calcula su firma (movimiento/músculo/equipo) y
 * la puntúa contra TODOS los ejercicios de la BD (signaturas precalculadas y
 * cacheadas). El resultado es un ranking con niveles:
 *
 *   A  nombre normalizado idéntico
 *   B  firma igual (movimiento+músculo+equipo compatibles)
 *   C  contención de nombres + movimiento compatible
 *   D  solo movimiento igual (músculo/equipo ignorados)
 *   E  solo músculo igual (movimiento desconocido en fuente)
 *
 * confianza = base(nivel) + bonus*similaridad(tokens/levenshtein).
 * Se devuelve el mejor candidato si supera el umbral.
 */
final class ExerciseMatcher
{
    public const LEVELS = ['A', 'B', 'C', 'D', 'E'];

    private array $dbSignatures;
    private float $threshold;

    public function __construct(float $threshold = 0.72)
    {
        $this->threshold = $threshold;
        $this->dbSignatures = $this->loadDbSignatures();
    }

    /**
     * @return array{level:string, confidence:float, score:float, exercise:object, signature:Signature}
     *              |null
     */
    public function match(string $title, ?string $sourceEquipment = null, array $sourceMuscles = [], ?string $mappedEquipment = null): ?array
    {
        $ranked = $this->candidates($title, $sourceEquipment, $sourceMuscles, $mappedEquipment, 1);

        return $ranked[0] ?? null;
    }

    /**
     * Devuelve los mejores candidatos ordenados por confianza (desc), aunque
     * no superen el umbral. Útil para el reporte y para depurar.
     *
     * @return array<int, array{level:string, confidence:float, score:float, exercise:object, signature:Signature}>
     */
    public function candidates(string $title, ?string $sourceEquipment = null, array $sourceMuscles = [], ?string $mappedEquipment = null, int $limit = 5): array
    {
        $source = Signature::fromSource($title, $sourceEquipment, $sourceMuscles, $mappedEquipment);

        $bestPerExercise = [];
        foreach ($this->dbSignatures as $sig) {
            $level = $this->level($source, $sig['signature']);
            if ($level === null) {
                continue;
            }
            $sim = $this->similarity($source, $sig['signature']);
            $confidence = $this->confidence($level, $sim);
            $id = $sig['exercise']->id;
            if (!isset($bestPerExercise[$id]) || $confidence > $bestPerExercise[$id]['confidence']) {
                $bestPerExercise[$id] = [
                    'level'      => $level,
                    'confidence' => round($confidence, 4),
                    'score'      => round($sim, 4),
                    'exercise'   => $sig['exercise'],
                    'signature'  => $sig['signature'],
                ];
            }
        }

        $ranked = array_values($bestPerExercise);
        usort($ranked, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        $ranked = array_slice($ranked, 0, max(1, $limit));

        // solo los que superan el umbral
        return array_values(array_filter($ranked, fn ($r) => $r['confidence'] >= $this->threshold));
    }

    /** Top-K sin filtrar por umbral (para reporte de no-matcheados). */
    public function topCandidates(string $title, ?string $sourceEquipment = null, array $sourceMuscles = [], ?string $mappedEquipment = null, int $limit = 5): array
    {
        $threshold = $this->threshold;
        $this->threshold = 0.0;
        try {
            return $this->candidates($title, $sourceEquipment, $sourceMuscles, $mappedEquipment, $limit);
        } finally {
            $this->threshold = $threshold;
        }
    }

    /** Nivel de la coincidencia fuente<->BD (null si no hay vínculo). */
    private function level(Signature $source, Signature $db): ?string
    {
        $sTokens = $source->tokens;
        $dTokens = $db->tokens;

        // A: nombre normalizado idéntico (ignorando stopwords)
        if ($sTokens !== [] && $dTokens !== [] && $sTokens === $dTokens) {
            return 'A';
        }

        // E: solo músculo (movimiento de fuente desconocido)
        if ($source->movement === null && $source->muscle !== null) {
            if ($db->muscle !== null && $db->muscle === $source->muscle) {
                return 'E';
            }
        }

        if ($source->movement === null) {
            return null;
        }

        if ($db->movement === null) {
            return null;
        }

        // B: firma compatible (movimiento igual, músculo y equipo compatibles)
        if ($source->movement === $db->movement && $this->compatible($source->muscle, $db->muscle) && $this->compatible($source->equipment, $db->equipment)) {
            return 'B';
        }

        // C: contención de nombre + movimiento compatible
        if ($source->movement === $db->movement) {
            $s = implode(' ', $sTokens);
            $d = implode(' ', $dTokens);
            if ($sTokens !== [] && $dTokens !== [] && (str_contains($s, $d) || str_contains($d, $s)) && strlen(min($s, $d)) >= 4) {
                return 'C';
            }
        }

        // D: solo movimiento igual
        if ($source->movement === $db->movement) {
            return 'D';
        }

        return null;
    }

    private function compatible(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return true;
        }
        return $a === $b;
    }

    /** Similaridad 0..1 combinando Jaccard de tokens (expandidos) y Levenshtein. */
    private function similarity(Signature $source, Signature $db): float
    {
        $s = $source->expandedTokens;
        $d = $db->expandedTokens;
        $jaccard = $this->jaccard($s, $d);

        $sNorm = implode(' ', $source->tokens);
        $dNorm = implode(' ', $db->tokens);
        $lev = $this->levRatio($sNorm, $dNorm);

        return ($jaccard * 0.6) + ($lev * 0.4);
    }

    private function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $intersect = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));
        if ($union === 0) {
            return 0.0;
        }

        return $intersect / $union;
    }

    private function levRatio(string $a, string $b): float
    {
        if ($a === '' && $b === '') {
            return 1.0;
        }
        if ($a === '' || $b === '') {
            return 0.0;
        }
        $max = max(mb_strlen($a), mb_strlen($b));
        if ($max === 0) {
            return 1.0;
        }
        $dist = $this->levenshteinUtf8($a, $b);

        return 1 - ($dist / $max);
    }

    private function levenshteinUtf8(string $a, string $b): int
    {
        return levenshtein($a, $b);
    }

    private function confidence(string $level, float $sim): float
    {
        return match ($level) {
            'A' => 1.0,
            'B' => 0.82 + (0.15 * $sim),
            'C' => 0.75 + (0.15 * $sim),
            'D' => 0.55 + (0.30 * $sim),
            'E' => 0.45 + (0.30 * $sim),
            default => 0.0,
        };
    }

    /** Signaturas de BD precalculadas (cacheadas 1 hora). */
    private function loadDbSignatures(): array
    {
        return Cache::remember('exercise_matcher_db_signatures_v1', 3600, function () {
            $exercises = Exercise::withTrashed()->get();

            // map equipment id => título
            $equipment = Equipment::pluck('title', 'id')->all();

            // map body part id => title (los ejercicios guardan ids en JSON bodypart_ids)
            $bpNames = BodyPart::pluck('title', 'id')->all();

            $sigs = [];
            foreach ($exercises as $ex) {
                $eqTitle = $ex->equipment_id ? ($equipment[$ex->equipment_id] ?? null) : null;
                $muscles = [];
                foreach ((array) ($ex->bodypart_ids ?? []) as $bpId) {
                    if (isset($bpNames[$bpId])) {
                        $muscles[] = $bpNames[$bpId];
                    }
                }
                $sig = Signature::fromDb($ex->title, $eqTitle, $muscles);
                $sigs[] = [
                    'exercise'  => $ex,
                    'signature' => $sig,
                ];
            }

            return $sigs;
        });
    }
}
