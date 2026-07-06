<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal;

use Directorium\Core\Temporal\TemporalDefinitions;
use PHPUnit\Framework\TestCase;

/**
 * The temporal-skeleton read seam (#42): the Easter offsets and block->season
 * assignments read from the corpus. The exact values are also exercised end to
 * end by PaschalSkeletonTest and SeasonTest (and locked byte-for-byte by the
 * golden fixture); this pins the reader's own shape.
 */
final class TemporalDefinitionsTest extends TestCase
{
    public function testEasterOffsetsCarryTheAnchorDeltas(): void
    {
        $offsets = TemporalDefinitions::default()->easterOffsets();

        self::assertSame(0, $offsets['easter']);
        self::assertSame(-63, $offsets['septuagesima']);
        self::assertSame(-46, $offsets['ash-wednesday']);
        self::assertSame(49, $offsets['pentecost']);
        self::assertSame(68, $offsets['sacred-heart']);
        self::assertCount(30, $offsets);
    }

    public function testBlockSeasonsMapEachBlockToItsSeason(): void
    {
        $blockSeasons = TemporalDefinitions::default()->blockSeasons();

        self::assertSame('advent', $blockSeasons['advent']);
        self::assertSame('epiphany', $blockSeasons['time-after-epiphany']);
        // Passion Week and Holy Week are both Passiontide.
        self::assertSame('passiontide', $blockSeasons['passiontide']);
        self::assertSame('passiontide', $blockSeasons['holy-week']);
        self::assertSame('pentecost', $blockSeasons['time-after-pentecost']);
        self::assertCount(9, $blockSeasons);
    }
}
