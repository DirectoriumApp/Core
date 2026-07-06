<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Sanctoral;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Sanctoral\CorpusSanctoralData;
use Directorium\Core\Sanctoral\SanctoralCalendar;
use Directorium\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

/**
 * The 1954 (Divino Afflatu) sanctoral OCTAVE subsystem (#65 — the safer-split design).
 *
 * A sanctoral octave is materialised as pure DATA — the generator expands each declared
 * octave into identity/attributes/placement records exactly as a vigil is — so the loader
 * places its days on the calendar grid with no new runtime engine. This proves the data
 * lands on the right dates at the right grade, that the {@see octaveOf} link threads
 * through to the realized observance, and that the 1960 edition (which keeps no sanctoral
 * octaves) is unaffected even though the shared identity now lists them.
 */
final class OctaveEditionTest extends TestCase
{
    private const DIVINO_AFFLATU = 'roman-divino-afflatu';

    private function nineteenFiftyFour(): SanctoralCalendar
    {
        return SanctoralCalendar::forYear(1954, new CorpusSanctoralData(null, self::DIVINO_AFFLATU));
    }

    /**
     * @return list<SanctoralObservance>
     */
    private function on(SanctoralCalendar $calendar, string $date): array
    {
        return $calendar->on(new DateTimeImmutable($date, new DateTimeZone('UTC')));
    }

    /**
     * @param list<SanctoralObservance> $offices
     */
    private function observanceFor(array $offices, string $id): ?SanctoralObservance
    {
        foreach ($offices as $office) {
            if ($office->id()->toString() === $id) {
                return $office;
            }
        }

        return null;
    }

    public function testACommonOctaveDayIsAGreaterDoubleLinkedToItsFeast(): void
    {
        // Octave Day of the Nativity of St John the Baptist — June 24 + 7 = July 1.
        $octaveDay = $this->observanceFor(
            $this->on($this->nineteenFiftyFour(), '1954-07-01'),
            'roman:sanctorale:nativitas-ioannis-baptistae:in-octava'
        );

        self::assertInstanceOf(SanctoralObservance::class, $octaveDay);
        self::assertSame('octave-day', $octaveDay->kind()->value());
        self::assertSame('duplex-maius', $octaveDay->legacyRank()->value());
        self::assertSame(3, $octaveDay->rank()->ordinal());
        self::assertSame('white', $octaveDay->colour()->base()->value());
        self::assertSame('In Octava Nativitatis S. Ioannis Baptistae', $octaveDay->latinName());
        self::assertNotNull($octaveDay->octaveOfId());
        self::assertSame('roman:sanctorale:nativitas-ioannis-baptistae', $octaveDay->octaveOfId()->toString());
    }

    public function testADayWithinACommonOctaveIsASemidoubleInTheFeastColour(): void
    {
        // Dies quarta infra Octavam Assumptionis — August 15 + 3 = August 18.
        $within = $this->observanceFor(
            $this->on($this->nineteenFiftyFour(), '1954-08-18'),
            'roman:sanctorale:assumptio:infra-octavam:4'
        );

        self::assertInstanceOf(SanctoralObservance::class, $within);
        self::assertSame('within-octave', $within->kind()->value());
        self::assertSame('semiduplex', $within->legacyRank()->value());
        self::assertSame(3, $within->rank()->ordinal());
        self::assertSame('white', $within->colour()->base()->value());
        self::assertSame('Dies quarta infra Octavam Assumptionis B.M.V.', $within->latinName());
        self::assertSame('roman:sanctorale:assumptio', $within->octaveOfId()->toString());
    }

    public function testASimpleOctaveKeepsOnlyItsOctaveDayAsASimple(): void
    {
        // St Lawrence: a simple octave — only the octave day (Aug 10 + 7 = Aug 17), a simple.
        $octaveDay = $this->observanceFor(
            $this->on($this->nineteenFiftyFour(), '1954-08-17'),
            'roman:sanctorale:laurentius:in-octava'
        );

        self::assertInstanceOf(SanctoralObservance::class, $octaveDay);
        self::assertSame('simplex', $octaveDay->legacyRank()->value());
        self::assertSame(4, $octaveDay->rank()->ordinal());
        self::assertSame('roman:sanctorale:laurentius', $octaveDay->octaveOfId()->toString());

        // A simple octave has NO proper days within: nothing on Aug 11-16 links to Lawrence.
        for ($day = 11; $day <= 16; $day++) {
            $offices = $this->on($this->nineteenFiftyFour(), sprintf('1954-08-%02d', $day));
            foreach ($offices as $office) {
                if ($office->octaveOfId() !== null) {
                    self::assertNotSame(
                        'roman:sanctorale:laurentius',
                        $office->octaveOfId()->toString(),
                        sprintf('A simple octave keeps no day within; Aug %d did.', $day)
                    );
                }
            }
        }
    }

    public function testTheDeferredOctaveDaysAreNotPlaced(): void
    {
        $calendar = $this->nineteenFiftyFour();

        // The Assumption octave day (Aug 22) is displaced by the Immaculate Heart and is held
        // for #64/#67; only its days within are materialised now.
        self::assertNull(
            $this->observanceFor($this->on($calendar, '1954-08-22'), 'roman:sanctorale:assumptio:in-octava'),
            'The Assumption octave day (Aug 22) is deferred and must not be placed.'
        );

        // The Nativity BVM simple octave is deferred whole (its Sep 15 day is Seven Sorrows').
        foreach ($this->on($calendar, '1954-09-15') as $office) {
            self::assertStringNotContainsString(
                'nativitas-mariae:in-octava',
                $office->id()->toString(),
                'The Nativity BVM octave day (Sep 15) is deferred and must not be placed.'
            );
        }
    }

    public function testTheNineteenSixtyEditionPlacesNoSanctoralOctave(): void
    {
        // The shared identity now lists the octave observances (the cross-edition union), but
        // the 1960 edition places none — so July 1 1962 carries no octave office.
        $offices = $this->on(SanctoralCalendar::forYear(1962), '1962-07-01');
        foreach ($offices as $office) {
            self::assertNull(
                $office->octaveOfId(),
                sprintf('The 1960 edition keeps no octave; %s carried an octave link.', $office->id()->toString())
            );
        }
    }
}
