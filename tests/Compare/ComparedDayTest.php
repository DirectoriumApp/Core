<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Compare;

use Directorium\Core\Compare\ComparedDay;
use Directorium\Core\Compare\ComparisonField;
use Directorium\Core\Compare\EditionDayCell;
use Directorium\Core\Temporal\TemporalCalendar;
use PHPUnit\Framework\TestCase;

/**
 * The divergence taxonomy in isolation (#312): {@see ComparedDay} over hand-built
 * {@see EditionDayCell}s, so the per-field agreement logic is tested without the engine.
 */
final class ComparedDayTest extends TestCase
{
    /** Cells with identical comparable values diverge on nothing. */
    public function testAllAgree(): void
    {
        $day = $this->compare([
            'a' => $this->cell('a', 'roman:sanctorale:x', 3, 'white', ['roman:sanctorale:c'], 'pentecost'),
            'b' => $this->cell('b', 'roman:sanctorale:x', 3, 'white', ['roman:sanctorale:c'], 'pentecost'),
        ]);

        self::assertFalse($day->isDivergent());
        self::assertSame([], $day->divergences());
    }

    /** A lone differing field is the only one reported. */
    public function testOnlyTheDifferingFieldIsReported(): void
    {
        $day = $this->compare([
            'a' => $this->cell('a', 'roman:sanctorale:x', 2, 'white', [], 'pentecost'),
            'b' => $this->cell('b', 'roman:sanctorale:x', 4, 'white', [], 'pentecost'),
        ]);

        self::assertSame([ComparisonField::RANK], $day->divergences());
    }

    /** Commemorations compare as a set: the same ids in a different order still agree. */
    public function testCommemorationsCompareOrderIndependently(): void
    {
        $p = 'roman:sanctorale:p';
        $q = 'roman:sanctorale:q';
        $day = $this->compare([
            'a' => $this->cell('a', 'roman:sanctorale:x', 3, 'red', [$p, $q], 'lent'),
            'b' => $this->cell('b', 'roman:sanctorale:x', 3, 'red', [$q, $p], 'lent'),
        ]);

        self::assertTrue($day->agreesOn(ComparisonField::COMMEMORATIONS));
        self::assertFalse($day->isDivergent());
    }

    /** A day with no celebration (null feast) diverges from one that has a feast. */
    public function testEmptyCelebrationDivergesFromAFeast(): void
    {
        $empty = EditionDayCell::fromContract('a', ['season' => null, 'celebration' => [], 'commemoration' => []]);
        $feast = $this->cell('b', 'roman:sanctorale:x', 3, 'white', [], 'pentecost');

        $day = $this->compare(['a' => $empty, 'b' => $feast]);

        self::assertNull($empty->feastId());
        self::assertFalse($day->agreesOn(ComparisonField::FEAST));
        self::assertFalse($day->agreesOn(ComparisonField::SEASON));
    }

    /**
     * @param array<string, EditionDayCell> $cells
     */
    private function compare(array $cells): ComparedDay
    {
        return ComparedDay::of(TemporalCalendar::utcDate(2024, 6, 1), $cells);
    }

    /**
     * @param list<string> $commemorations
     */
    private function cell(
        string $edition,
        string $feast,
        int $rank,
        string $colour,
        array $commemorations,
        string $season
    ): EditionDayCell {
        return EditionDayCell::fromContract($edition, [
            'season' => $season,
            'celebration' => [[
                'id' => $feast,
                'rankOrdinal' => $rank,
                'colour' => ['base' => $colour],
                'names' => ['la' => $feast],
            ]],
            'commemoration' => array_map(
                static fn (string $id): array => ['id' => $id],
                $commemorations
            ),
        ]);
    }
}
