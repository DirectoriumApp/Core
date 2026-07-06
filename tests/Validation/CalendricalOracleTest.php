<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use Directorium\Core\Calendrical\CalendricalYear;
use PHPUnit\Framework\TestCase;

/**
 * Validation harness (#45), calendrical oracle (#246) — the year's cyclic numbers
 * cross-checked against published tables.
 *
 * Each row is the full calendrical block (Golden Number, Epact, Solar Cycle,
 * Dominical Letter, Roman Indiction) as printed in the front matter of the
 * Martyrologium Romanum and standard ecclesiastical almanacs (source
 * `calendrical-tables`), spanning a century year, the 1962 liturgical baseline,
 * and a run of recent years. The numbers are edition-invariant — a fact of the
 * Gregorian reckoning, not of any rubric system — so this oracle carries no
 * edition group.
 */
final class CalendricalOracleTest extends TestCase
{
    /**
     * @dataProvider publishedBlocks
     */
    public function testTheCalendricalBlockMatchesThePrintedTables(
        int $year,
        int $goldenNumber,
        int $epact,
        int $solarCycle,
        string $dominicalLetter,
        int $romanIndiction
    ): void {
        $block = CalendricalYear::forYear($year);

        self::assertSame($goldenNumber, $block->goldenNumber(), "golden number $year");
        self::assertSame($epact, $block->epact(), "epact $year");
        self::assertSame($solarCycle, $block->solarCycle(), "solar cycle $year");
        self::assertSame($dominicalLetter, $block->dominicalLetter(), "dominical letter $year");
        self::assertSame($romanIndiction, $block->romanIndiction(), "roman indiction $year");
    }

    /**
     * Published calendrical numbers (source `calendrical-tables`):
     * [year, Golden Number, Epact, Solar Cycle, Dominical Letter, Roman Indiction].
     *
     * @return array<string, array{int, int, int, int, string, int}>
     */
    public function publishedBlocks(): array
    {
        return [
            'AD 1900 (century year)' => [1900, 1, 29, 5, 'G', 13],
            'AD 1962 (liturgical baseline)' => [1962, 6, 24, 11, 'G', 15],
            'AD 2000 (leap)' => [2000, 6, 24, 21, 'BA', 8],
            'AD 2010' => [2010, 16, 14, 3, 'C', 3],
            'AD 2024 (leap)' => [2024, 11, 19, 17, 'GF', 2],
            'AD 2025' => [2025, 12, 0, 18, 'E', 3],
        ];
    }
}
