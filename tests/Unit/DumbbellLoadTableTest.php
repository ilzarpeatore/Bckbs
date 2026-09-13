<?php

namespace Tests\Unit;

use App\Support\DumbbellLoadTable;
use PHPUnit\Framework\TestCase;

class DumbbellLoadTableTest extends TestCase
{
    /** @dataProvider snapCases */
    public function test_snaps_to_nearest_dumbbell_rack_value(float $raw, float $expected): void
    {
        $this->assertSame($expected, DumbbellLoadTable::snap($raw));
    }

    public static function snapCases(): array
    {
        return [
            'low tier rounds to nearest 1kg' => [6.4, 6.0],
            'low tier boundary stays at 15' => [15.0, 15.0],
            'just above 15 snaps to next 2.5kg step' => [16.3, 17.5],
            'mid high tier rounds to nearest 2.5kg' => [18.9, 20.0],
            'clamps below the rack minimum' => [0.2, 1.0],
            'clamps above the rack maximum' => [55.0, 50.0],
        ];
    }
}
