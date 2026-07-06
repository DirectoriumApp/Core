<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Sanctoral;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Temporal\ChristmasCycle;
use Directorium\Core\Temporal\Eastertide;
use Directorium\Core\Temporal\TemporalObservance;
use Directorium\Core\Sanctoral\SanctoralCalendar;
use Directorium\Core\Tests\Fixture\SeedSanctoralData;
use PHPUnit\Framework\TestCase;

/**
 * Octave handling under the 1960 rubrics (#28). Only three octaves survive —
 * Christmas, Easter, and Pentecost — and all three are temporal (the fillers
 * emit their days). The sanctoral overlay therefore generates no octaves at
 * all; these tests lock that in and check the Christmas-octave interaction the
 * resolver (#29) will resolve.
 */
final class SanctoralOctaveTest extends TestCase
{
    public function testOverlayEmitsNoSanctoralOctaves(): void
    {
        foreach (SanctoralCalendar::forYear(2025, new SeedSanctoralData())->all() as $ymd => $offices) {
            foreach ($offices as $office) {
                self::assertNotSame('octave-day', $office->kind()->value(), $ymd);
                self::assertNotSame('within-octave', $office->kind()->value(), $ymd);
            }
        }
    }

    /**
     * A day within each of the three surviving octaves is emitted by the
     * temporal layer as an octave day.
     */
    public function testTheThreeSurvivingOctavesAreEmittedByTheTemporalLayer(): void
    {
        // Christmas octave: 27 December is within the octave of the Nativity.
        self::assertSame(
            'within-octave',
            self::temporalKind(ChristmasCycle::forYear(2024)->on(self::utc('2024-12-27')))
        );
        // Easter octave 2025 (Easter 20 Apr): Easter Monday is within the octave.
        self::assertSame(
            'within-octave',
            self::temporalKind(Eastertide::forYear(2025)->on(self::utc('2025-04-21')))
        );
        // Pentecost octave 2025 (Pentecost 8 Jun): Whit Monday is within the octave.
        self::assertSame(
            'within-octave',
            self::temporalKind(Eastertide::forYear(2025)->on(self::utc('2025-06-09')))
        );
    }

    /**
     * Where a sanctoral feast falls within the Christmas octave, both layers
     * produce an office for the day — the co-occurrence the resolver resolves
     * (feast celebrated, octave commemorated).
     */
    public function testChristmasOctaveCoOccursWithItsSanctoralFeasts(): void
    {
        $temporal = ChristmasCycle::forYear(2024)->on(self::utc('2024-12-26'));
        self::assertNotNull($temporal);
        self::assertSame('within-octave', $temporal->kind()->value());

        $sanctoral = SanctoralCalendar::forYear(2024, new SeedSanctoralData())->on(self::utc('2024-12-26'));
        self::assertCount(1, $sanctoral);
        self::assertSame('roman:sanctorale:stephanus', $sanctoral[0]->id()->toString());
    }

    private static function temporalKind(?TemporalObservance $office): string
    {
        self::assertNotNull($office);

        return $office->kind()->value();
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
