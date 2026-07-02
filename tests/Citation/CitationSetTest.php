<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Citation;

use Introibo\Core\Citation\CitationSet;
use PHPUnit\Framework\TestCase;

final class CitationSetTest extends TestCase
{
    public function testBuildsFromMarkersAndResolvesFields(): void
    {
        $set = CitationSet::fromMarkers([
            'names.la' => 'mr-1920',
            'rank' => 'rg-1960',
            'colour' => 'rg-1960:n.117',
        ]);

        self::assertFalse($set->isEmpty());
        self::assertTrue($set->has('names.la'));
        self::assertSame('mr-1920', $set->for('names.la')->sourceKey());
        self::assertSame('rg-1960', $set->for('rank')->sourceKey());
        self::assertSame('n.117', $set->for('colour')->locator());
    }

    public function testMissingFieldIsNull(): void
    {
        $set = CitationSet::fromMarkers(['rank' => 'rg-1960']);

        self::assertNull($set->for('colour'));
        self::assertFalse($set->has('colour'));
    }

    public function testFieldsPreserveInsertionOrder(): void
    {
        $set = CitationSet::fromMarkers([
            'names.la' => 'mr-1920',
            'month' => 'rg-1960',
            'day' => 'rg-1960',
        ]);

        self::assertSame(['names.la', 'month', 'day'], $set->fields());
    }

    public function testEmptySet(): void
    {
        $set = CitationSet::empty();

        self::assertTrue($set->isEmpty());
        self::assertSame([], $set->fields());
        self::assertSame([], $set->all());
    }

    public function testAllReturnsEveryCitation(): void
    {
        $set = CitationSet::fromMarkers(['rank' => 'rg-1960', 'colour' => 'rg-1960']);

        $all = $set->all();
        self::assertArrayHasKey('rank', $all);
        self::assertArrayHasKey('colour', $all);
        self::assertSame('rg-1960', $all['rank']->sourceKey());
    }
}
