<?php

namespace Tests\Unit;

use App\Support\FuzzySearch;
use PHPUnit\Framework\TestCase;

class FuzzySearchTest extends TestCase
{
    public function test_normalize_quita_acentos_mayusculas_y_signos(): void
    {
        $this->assertSame('elevacion lateral', FuzzySearch::normalize('  Elevación  LATERAL '));
        $this->assertSame('pena canon', FuzzySearch::normalize('Peña-Cañón'));
        $this->assertSame('press de banca', FuzzySearch::normalize('Press de banca!'));
        $this->assertSame('', FuzzySearch::normalize(null));
    }

    public function test_distancia_damerau_levenshtein(): void
    {
        $this->assertSame(0, FuzzySearch::distance('lateral', 'lateral'));
        $this->assertSame(1, FuzzySearch::distance('lateal', 'lateral'));   // letra que falta
        $this->assertSame(1, FuzzySearch::distance('bnaca', 'banca') === 1 ? 1 : 99); // transposición
        $this->assertSame(1, FuzzySearch::distance('banac', 'banca'));       // transposición
        $this->assertSame(3, FuzzySearch::distance('kitten', 'sitting'));
    }

    public function test_presupuesto_de_erratas_segun_longitud(): void
    {
        $this->assertSame(0, FuzzySearch::budget(3));
        $this->assertSame(1, FuzzySearch::budget(4));
        $this->assertSame(1, FuzzySearch::budget(7));
        $this->assertSame(2, FuzzySearch::budget(8));
    }

    /** @dataProvider casos */
    public function test_score(string $query, array $fields, bool $matches): void
    {
        $this->assertSame($matches, FuzzySearch::score($query, $fields) !== null, "consulta: {$query}");
    }

    public static function casos(): array
    {
        $ex = ['Elevación lateral con mancuernas'];

        return [
            'el caso reportado: sin tilde y con errata' => ['Elevacion lateal', $ex, true],
            'sin tilde'                                 => ['elevacion lateral', $ex, true],
            'con tilde'                                 => ['ELEVACIÓN', $ex, true],
            'palabras en otro orden'                    => ['mancuernas elevacion', $ex, true],
            'a medio escribir con errata'               => ['elevaci lateral', $ex, true],
            'transposicion'                             => ['mancuenras', $ex, true],
            'palabra corta no admite errata'            => ['pres', ['Press de banca'], true],   // contenida
            'palabra corta distinta no encaja'          => ['pra', ['Press de banca'], false],
            'demasiadas erratas'                        => ['sentadilla', ['Press de banca'], false],
            'una palabra que falta impide el match'     => ['elevacion frontal', $ex, false],
            'varios campos'                             => ['juan garcia', ['Juan', 'García López'], true],
            'consulta vacia encaja siempre'             => ['   ', $ex, true],
        ];
    }

    public function test_puntuacion_ordena_exacto_antes_que_erratas(): void
    {
        $exacto = FuzzySearch::score('lateral', ['Elevación lateral']);
        $errata = FuzzySearch::score('lateal', ['Elevación lateral']);

        $this->assertSame(0, $exacto);
        $this->assertSame(1, $errata);
    }
}
