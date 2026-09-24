<?php

namespace Tests\Unit;

use App\Services\ProgramSessionMatrixService;
use PHPUnit\Framework\TestCase;

class ProgramSessionMatrixServiceTest extends TestCase
{
    /** @dataProvider titles */
    public function test_session_stem_quita_programa_y_variante_de_progresion(string $title, string $key, string $label): void
    {
        [$k, $l] = ProgramSessionMatrixService::sessionStem($title);

        $this->assertSame($key, $k);
        $this->assertSame($label, $l);
    }

    public static function titles(): array
    {
        return [
            'programa · sesión (S#)' => ['Mesociclo 1 TONI Septiembre · Torso A (S2)', 'torso a', 'Torso A'],
            'el paréntesis propio de la sesión se conserva' => [
                'Mesociclo 1 ALBERTO Octubre -- Torso/Pierna x2 + Full Body · Torso A (Empuje/Traccion) (S3)',
                'torso a (empuje/traccion)', 'Torso A (Empuje/Traccion)',
            ],
            'el nombre del programa puede contener " · "' => [
                'Be Stronger — Macrociclo 2 · Mesociclo 1 (M1) · Tracción (S4)', 'tracción', 'Tracción',
            ],
            'misma sesión en distintas variantes comparte clave' => ['P · Empuje (S1)', 'empuje', 'Empuje'],
            'sin separador ni variante' => ['Full Body', 'full body', 'Full Body'],
            'solo variante cae al título original' => ['(S1)', '(s1)', '(S1)'],
        ];
    }

    public function test_variantes_de_la_misma_sesion_comparten_clave(): void
    {
        $a = ProgramSessionMatrixService::sessionStem('Prog · Empuje (S1)');
        $b = ProgramSessionMatrixService::sessionStem('Prog · Empuje (S4)');

        $this->assertSame($a[0], $b[0]);
    }
}
