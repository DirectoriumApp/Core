<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Compare;

use Directorium\Core\Compare\ComparedSequence;
use Directorium\Core\Compare\ComparisonField;
use Directorium\Core\Compare\SequenceComparator;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Temporal\TemporalCalendar;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Year-sequence comparison for the slider (#313). Fixtures are pinned to real behaviour
 * of the built engine: 4 February is Andreas Corsini (III, Epiphanytide) in most years,
 * but in 2024 the early Easter pulls Sexagesima onto it (II, Septuagesima) — so a fixed
 * civil date visibly drifts through the movable cycle.
 */
final class SequenceComparatorTest extends TestCase
{
    private const E1962 = RubricSystem::RUBRICAE_1960;
    private const E1954 = RubricSystem::DIVINO_AFFLATU;
    private const E1955 = RubricSystem::RUBRICAE_1955;

    /** Scrubbing 4 February across 2022–2025 under 1962 tags exactly the years that change. */
    public function testAcrossYearsTagsChanges(): void
    {
        $sequence = (new SequenceComparator())->acrossYears(
            TemporalCalendar::utcDate(2024, 2, 4),
            2022,
            2025,
            '1962'
        );

        self::assertSame(ComparedSequence::AXIS_YEAR, $sequence->axis());
        self::assertSame(4, $sequence->length());

        $keys = array_map(static fn ($p): string => $p->key(), $sequence->points());
        self::assertSame(['2022', '2023', '2024', '2025'], $keys);

        $points = $sequence->points();
        self::assertSame([], $points[0]->changes(), 'the first point has no predecessor');
        self::assertFalse($points[1]->changed(), '2023 resolves like 2022');
        self::assertTrue($points[2]->changed(), '2024 pulls Sexagesima onto 4 Feb');
        self::assertTrue($points[3]->changed(), '2025 reverts to Andreas Corsini');
        self::assertCount(2, $sequence->changedPoints());

        self::assertSame('roman:sanctorale:andreas-corsini', $points[0]->cell()->feastId());
        self::assertSame('epiphany', $points[0]->cell()->season());
        self::assertSame('roman:temporale:paschal:sexagesima', $points[2]->cell()->feastId());
        self::assertSame('septuagesima', $points[2]->cell()->season());
        self::assertContains(ComparisonField::SEASON, $points[2]->changes());
        self::assertSame('2024-02-04', $points[2]->date()->format('Y-m-d'));
    }

    /** A 29 February anchor yields a point only in the leap years of the span. */
    public function testAcrossYearsSkipsDatesThatDoNotExist(): void
    {
        $sequence = (new SequenceComparator())->acrossYears(
            TemporalCalendar::utcDate(2024, 2, 29),
            2022,
            2025,
            '1962'
        );

        self::assertSame(1, $sequence->length());
        self::assertSame('2024', $sequence->points()[0]->key());
    }

    /** Scrubbing 8 November across the editions tags where each reform changes the day. */
    public function testAcrossEditionsTagsChanges(): void
    {
        $sequence = (new SequenceComparator())->acrossEditions(
            TemporalCalendar::utcDate(2024, 11, 8),
            ['1962', '1954', '1955']
        );

        self::assertSame(ComparedSequence::AXIS_EDITION, $sequence->axis());
        self::assertSame(3, $sequence->length());

        $keys = array_map(static fn ($p): string => $p->key(), $sequence->points());
        self::assertSame([self::E1962, self::E1954, self::E1955], $keys);

        $points = $sequence->points();
        self::assertSame([], $points[0]->changes());
        self::assertTrue($points[1]->changed(), '1954 keeps the Octave of All Saints');
        self::assertContains(ComparisonField::FEAST, $points[1]->changes());
        self::assertContains(ComparisonField::COLOUR, $points[1]->changes());
        self::assertTrue($points[2]->changed(), '1955 drops the octave again');
    }

    public function testAcrossEditionsRejectsFewerThanTwoDistinctEditions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SequenceComparator())->acrossEditions(
            TemporalCalendar::utcDate(2024, 11, 8),
            ['1962', '1960']
        );
    }

    public function testAcrossYearsRejectsInvertedSpan(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SequenceComparator())->acrossYears(TemporalCalendar::utcDate(2024, 2, 4), 2025, 2020, '1962');
    }
}
