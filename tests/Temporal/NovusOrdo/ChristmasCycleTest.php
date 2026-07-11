<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal\NovusOrdo;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Temporal\NovusOrdo\ChristmasCycle;
use Directorium\Core\Temporal\NovusOrdo\OrdinaryTime;
use Directorium\Core\Temporal\NovusOrdoTemporalCycle;
use Directorium\Core\Temporal\PaschalSkeleton;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalCalendar;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The Novus-Ordo Advent → Christmas Time filler (#108): the four violet Advent Sundays
 * (Gaudete rose), the date-named privileged ferias of 17–24 December, the white Christmas
 * season from the Nativity to the Baptism of the Lord — its octave (date-named weekdays,
 * the Holy Family on the Sunday within, the octave day, the optional Second Sunday after
 * the Nativity), the Epiphany, and the weekdays after it.
 *
 * The offices are read from the REAL shipped Novus-Ordo temporal data
 * (data/corpus/editions/roman-novus-ordo-2002), which now carries the cited Advent/Christmas
 * archetypes alongside Ordinary Time. Dates are computed by hand from the Advent anchor and
 * the Baptism rule and cross-read against the published 2022–2025 calendars; the full-year
 * oracle sweep is #260.
 */
final class ChristmasCycleTest extends TestCase
{
    private const EDITION = 'roman-novus-ordo-2002';

    /** Advent that begins in the given civil year (Nativity in $year, Epiphany/Baptism in $year + 1). */
    private function cycle(int $year): ChristmasCycle
    {
        return ChristmasCycle::forYear($year, Corpus::default(), self::EDITION);
    }

    private static function utc(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
    }

    /** The four violet Advent Sundays, with the rose option on Gaudete (the third). */
    public function testAdventSundays(): void
    {
        $cycle = $this->cycle(2024); // Advent I = 1 Dec 2024

        $first = $cycle->on(self::utc('2024-12-01'));
        self::assertNotNull($first);
        self::assertSame('roman:temporale:advent:sunday-1', $first->id()->toString());
        self::assertSame('Dominica I Adventus', $first->latinName());
        self::assertSame(Season::ADVENT, $first->season()->value());
        self::assertSame('violet', $first->colour()->base()->value());
        self::assertFalse($first->colour()->roseAllowed());

        $gaudete = $cycle->on(self::utc('2024-12-15'));
        self::assertSame('roman:temporale:advent:sunday-3', $gaudete->id()->toString());
        self::assertSame('Dominica III Adventus', $gaudete->latinName());
        self::assertTrue($gaudete->colour()->roseAllowed(), 'Gaudete allows rose');

        self::assertSame('Dominica IV Adventus', $cycle->on(self::utc('2024-12-22'))->latinName());
    }

    /** A weekday before 17 December is named by week; the reform drops the "infra Hebdomadam" phrasing. */
    public function testAdventWeekdayBeforeTheSeventeenth(): void
    {
        $monday = $this->cycle(2024)->on(self::utc('2024-12-02')); // Monday of week 1

        self::assertSame('roman:temporale:advent:week-1:feria-2', $monday->id()->toString());
        self::assertSame('Feria II Hebdomadae I Adventus', $monday->latinName());
        self::assertSame('feria', $monday->kind()->value());
        self::assertSame('violet', $monday->colour()->base()->value());
    }

    /** 17–24 December: the privileged ferias of the O antiphons, named by calendar date. */
    public function testLateAdventFeriasAreNamedByDate(): void
    {
        $cycle = $this->cycle(2024);

        $dec17 = $cycle->on(self::utc('2024-12-17'));
        self::assertSame('roman:temporale:advent:december-17', $dec17->id()->toString());
        self::assertSame('Die 17 decembris', $dec17->latinName());
        self::assertSame('violet', $dec17->colour()->base()->value());

        // 22 Dec 2024 is the fourth Sunday, so the run skips it; 23 and 24 are date-named.
        self::assertSame('Die 23 decembris', $cycle->on(self::utc('2024-12-23'))->latinName());
        self::assertSame('Die 24 decembris', $cycle->on(self::utc('2024-12-24'))->latinName());
    }

    /**
     * A "short Advent": when the fourth Sunday of Advent falls on 24 December it stays the
     * Sunday (not a date-named feria), and the Christmas Eve Mass is the solemnity's own.
     */
    public function testShortAdventKeepsTheFourthSundayOnTheTwentyFourth(): void
    {
        // Advent 2023: Christmas 2023 is a Monday, so 24 Dec 2023 is the fourth Sunday.
        $dec24 = $this->cycle(2023)->on(self::utc('2023-12-24'));

        self::assertSame('roman:temporale:advent:sunday-4', $dec24->id()->toString());
        self::assertSame('Dominica IV Adventus', $dec24->latinName());
    }

    /** The Nativity and the Epiphany reuse the shared great-feast archetypes (aligned across editions). */
    public function testNativityAndEpiphanyAreTheSharedGreatFeasts(): void
    {
        $cycle = $this->cycle(2024);

        $nativity = $cycle->on(self::utc('2024-12-25'));
        self::assertSame('roman:temporale:christmas:nativity', $nativity->id()->toString());
        self::assertSame('In Nativitate Domini', $nativity->latinName());
        self::assertSame('white', $nativity->colour()->base()->value());
        self::assertSame(Season::CHRISTMASTIDE, $nativity->season()->value());

        $epiphany = $cycle->on(self::utc('2025-01-06'));
        self::assertSame('roman:temporale:epiphany:domini', $epiphany->id()->toString());
        self::assertSame('In Epiphania Domini', $epiphany->latinName());
        self::assertSame(
            Season::CHRISTMASTIDE,
            $epiphany->season()->value(),
            'the reform keeps the Epiphany in Christmas Time'
        );
    }

    /** The Christmas octave: date-named weekdays under the octave, the Holy Family on the Sunday within it. */
    public function testChristmasOctave(): void
    {
        $cycle = $this->cycle(2024);

        // 29 Dec 2024 is the Sunday within the octave — the Holy Family.
        $holyFamily = $cycle->on(self::utc('2024-12-29'));
        self::assertSame('roman:temporale:christmas:holy-family', $holyFamily->id()->toString());
        self::assertSame('In Festo Sanctae Familiae Iesu, Mariae et Ioseph', $holyFamily->latinName());
        self::assertSame('feast', $holyFamily->kind()->value());
        self::assertSame('white', $holyFamily->colour()->base()->value());

        // A weekday within the octave is date-named and typed "within-octave".
        $dec27 = $cycle->on(self::utc('2024-12-27'));
        self::assertSame('roman:temporale:christmas:december-27', $dec27->id()->toString());
        self::assertSame('Die 27 decembris', $dec27->latinName());
        self::assertSame('within-octave', $dec27->kind()->value());
        self::assertSame('white', $dec27->colour()->base()->value());

        self::assertSame('Die 30 decembris', $cycle->on(self::utc('2024-12-30'))->latinName());
    }

    /**
     * When the Nativity is a Sunday the octave holds no Sunday, so the Holy Family moves to
     * 30 December. Advent 2022: Christmas 2022 is a Sunday.
     */
    public function testHolyFamilyFallsOnTheThirtiethWhenTheOctaveHasNoSunday(): void
    {
        $cycle = $this->cycle(2022);

        self::assertSame('2022-12-30', $cycle->holyFamily()->format('Y-m-d'));
        $holyFamily = $cycle->on(self::utc('2022-12-30'));
        self::assertSame('roman:temporale:christmas:holy-family', $holyFamily->id()->toString());
        self::assertSame('In Festo Sanctae Familiae Iesu, Mariae et Ioseph', $holyFamily->latinName());
    }

    /** 1 January is the octave day of the Nativity — a white solemnity in the reform. */
    public function testOctaveDay(): void
    {
        $octaveDay = $this->cycle(2024)->on(self::utc('2025-01-01'));

        self::assertSame('roman:temporale:christmas:octave-day', $octaveDay->id()->toString());
        self::assertSame('In Octava Nativitatis Domini', $octaveDay->latinName());
        self::assertSame('octave-day', $octaveDay->kind()->value());
        self::assertSame('white', $octaveDay->colour()->base()->value());
    }

    /** The Second Sunday after the Nativity appears only when a Sunday falls 2–5 January. */
    public function testSecondSundayAfterChristmasWhenOneFalls(): void
    {
        // Advent 2024: 1 Jan 2025 is a Wednesday, so 5 Jan 2025 is the second Sunday.
        $cycle = $this->cycle(2024);

        $second = $cycle->on(self::utc('2025-01-05'));
        self::assertSame('roman:temporale:christmas:sunday-after-octave', $second->id()->toString());
        self::assertSame('Dominica II post Nativitatem', $second->latinName());
        self::assertSame('sunday', $second->kind()->value());

        // The weekdays 2–4 January are date-named in Christmas Time.
        $jan2 = $cycle->on(self::utc('2025-01-02'));
        self::assertSame('roman:temporale:christmas:january-2', $jan2->id()->toString());
        self::assertSame('Die 2 ianuarii', $jan2->latinName());
        self::assertSame('white', $jan2->colour()->base()->value());
    }

    /** In a year where no Sunday falls 2–5 January there is no Second Sunday after the Nativity. */
    public function testNoSecondSundayWhenNoneFalls(): void
    {
        // Advent 2023: 1 Jan 2024 is a Monday, so the days 2–5 Jan are all weekdays.
        $cycle = $this->cycle(2023);

        $jan2 = $cycle->on(self::utc('2024-01-02'));
        self::assertSame(
            'roman:temporale:christmas:january-2',
            $jan2->id()->toString(),
            'no Sunday falls, so 2 Jan stays a weekday'
        );
        self::assertSame('Die 2 ianuarii', $jan2->latinName());
    }

    /** After the Epiphany, the weekdays up to the Baptism are "post Epiphaniam", still white Christmas Time. */
    public function testWeekdaysAfterEpiphany(): void
    {
        $cycle = $this->cycle(2024);

        $jan7 = $cycle->on(self::utc('2025-01-07')); // Tuesday = Feria III
        self::assertSame('roman:temporale:epiphany:post-epiphaniam:feria-3', $jan7->id()->toString());
        self::assertSame('Feria III post Epiphaniam', $jan7->latinName());
        self::assertSame(Season::CHRISTMASTIDE, $jan7->season()->value());
        self::assertSame('white', $jan7->colour()->base()->value());
    }

    /** The Baptism of the Lord closes Christmas Time; the following Monday belongs to Ordinary Time. */
    public function testBaptismClosesTheSeason(): void
    {
        $cycle = $this->cycle(2024); // Baptism = Sunday after 6 Jan 2025 = 12 Jan 2025

        self::assertSame('2025-01-12', $cycle->baptism()->format('Y-m-d'));
        $baptism = $cycle->on(self::utc('2025-01-12'));
        self::assertSame('roman:temporale:epiphany:baptism-of-the-lord', $baptism->id()->toString());
        self::assertSame('In Baptismate Domini', $baptism->latinName());
        self::assertSame('feast', $baptism->kind()->value());

        self::assertNull($cycle->on(self::utc('2025-01-13')), 'the Monday after the Baptism opens Ordinary Time');
    }

    /** The First Sunday of Advent is the first filled day; the day before it is outside the block. */
    public function testBoundaries(): void
    {
        $cycle = $this->cycle(2024);

        self::assertNotNull($cycle->on(self::utc('2024-12-01')), 'Advent I is the first filled day');
        self::assertNull($cycle->on(self::utc('2024-11-30')), 'the eve of Advent I is outside the block');
    }

    /**
     * The Novus-Ordo Proper of Time tiles a civil year from the PREVIOUS Advent's Christmas
     * cycle (its January tail), Ordinary Time, and the current Advent — with no overlap.
     */
    public function testTemporalCycleTilesTheCivilYear(): void
    {
        $cycle = (new NovusOrdoTemporalCycle())->forYear(2025, Corpus::default(), self::EDITION);

        // January 2025 is owned by the 2024 Advent's Christmas cycle …
        self::assertSame(
            'roman:temporale:epiphany:baptism-of-the-lord',
            $cycle->office(self::utc('2025-01-12'))->id()->toString()
        );
        // … the green blocks are Ordinary Time …
        self::assertSame(
            'roman:temporale:ordinary-time:sunday-2',
            $cycle->office(self::utc('2025-01-19'))->id()->toString()
        );
        // … and December 2025 is owned by the current Advent.
        self::assertSame(
            'roman:temporale:advent:sunday-1',
            $cycle->office(self::utc('2025-11-30'))->id()->toString()
        );
        // The paschal half now fills the middle of the year and supplies the Triduum window:
        // Easter 2025 is 20 April, so Good Friday (18 April) is in the Triduum.
        self::assertTrue($cycle->isTriduum(self::utc('2025-04-18')), 'the NO cycle reports the Triduum');
    }

    public function testRejectsPreGregorianYear(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ChristmasCycle::forYear(1580, Corpus::default(), self::EDITION);
    }

    /**
     * The Christmas cycle and Ordinary Time tile a civil year with no gap and no overlap: every
     * day is owned by exactly one of the previous Advent's Christmas cycle, this year's Ordinary
     * Time, or this year's Advent — EXCEPT the paschal window (Ash Wednesday through Pentecost),
     * which the {@see \Directorium\Core\Temporal\NovusOrdo\PaschalCycle} fills (proved by the
     * whole-cycle sweep in {@see PaschalCycleTest}). Walking a whole year here proves the
     * Baptism → Ordinary Time seam and the Advent boundary meet exactly, and that these three
     * fillers leave precisely the paschal window free for the fourth.
     */
    public function testThreeFillersTileTheCivilYearWithNoGapOrOverlap(): void
    {
        $year = 2025;
        $previousChristmas = ChristmasCycle::forYear($year - 1, Corpus::default(), self::EDITION);
        $ordinaryTime = OrdinaryTime::forYear($year, Corpus::default(), self::EDITION);
        $currentAdvent = ChristmasCycle::forYear($year, Corpus::default(), self::EDITION);

        $skeleton = PaschalSkeleton::forYear($year);
        $ashWednesday = $skeleton->ashWednesday(); // 5 Mar 2025
        $pentecost = $skeleton->pentecost();       // 8 Jun 2025

        $date = TemporalCalendar::utcDate($year, 1, 1);
        $end = TemporalCalendar::utcDate($year, 12, 31);
        for (; $date <= $end; $date = TemporalCalendar::addDays($date, 1)) {
            $owners = 0;
            foreach ([$previousChristmas, $ordinaryTime, $currentAdvent] as $filler) {
                if ($filler->on($date) !== null) {
                    $owners++;
                }
            }

            $inPaschalWindow = $date >= $ashWednesday && $date <= $pentecost;
            $expected = $inPaschalWindow ? 0 : 1;
            self::assertSame(
                $expected,
                $owners,
                sprintf('%s should be owned by %d filler(s), was %d', $date->format('Y-m-d'), $expected, $owners)
            );
        }
    }
}
