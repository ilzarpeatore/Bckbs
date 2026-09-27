<?php

namespace Tests\Unit;

use App\Support\MacrocycleTitle;
use PHPUnit\Framework\TestCase;

class MacrocycleTitleTest extends TestCase
{
    /**
     * @dataProvider titles
     */
    public function test_parse(string $title, ?string $macro, ?int $meso): void
    {
        $parsed = MacrocycleTitle::parse($title);

        if ($macro === null) {
            $this->assertNull($parsed);
            return;
        }

        $this->assertNotNull($parsed);
        $this->assertSame($macro, $parsed['macrocycle']);
        $this->assertSame($meso, $parsed['mesocycle']);
    }

    public static function titles(): array
    {
        return [
            ['Macrociclo 2 - Mesociclo 1', 'Macrociclo 2', 1],
            ['Macrociclo 2 - Mesociclo 3 ', 'Macrociclo 2', 3],
            ['M1 (Be Stronger Macrociclo 2)', 'Be Stronger Macrociclo 2', 1],
            ['Carlos - Macrociclo 1 - Mesociclo 4', 'Carlos - Macrociclo 1', 4],
            ['Mesociclo 2 · Carlos', 'Carlos', 2],
            ['Carlos Meso 3', 'Carlos', 3],
            ['Macrociclo Carlos', 'Macrociclo Carlos', null],
            ['Mesociclo 1', 'Sin nombre', 1],
            ['Be Stronger — Macrociclo 2 · Mesociclo 1 (M1)', 'Be Stronger - Macrociclo 2', 1],
            ['Fuerza 5x5', null, null],
            ['Máquinas y mancuernas', null, null],
            ['', null, null],
        ];
    }

    public function test_same_macrocycle_shares_key_regardless_of_case(): void
    {
        $a = MacrocycleTitle::parse('Macrociclo 2 - Mesociclo 1');
        $b = MacrocycleTitle::parse('macrociclo 2 - mesociclo 2');

        $this->assertSame($a['key'], $b['key']);
    }
}
