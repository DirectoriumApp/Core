<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal\NovusOrdo;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Temporal\NovusOrdo\ChristmasCycle;
use Directorium\Core\Temporal\NovusOrdo\OrdinaryTime;
use Directorium\Core\Temporal\NovusOrdo\PaschalCycle;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalCalendar;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The Novus-Ordo paschal-half filler (#108): Lent (Ash Wednesday, the "post Cineres" ferias,
 * the five Sundays "in Quadragesima" with Laetare rose, the weekdays), Holy Week (the merged
 * red Palm Sunday, the "Hebdomadae Sanctae" weekdays, the Sacred Triduum), and Easter Time
 * (the octave celebrated as solemnities of the Lord, Divine Mercy Sunday, the Sundays and
 * weekdays of Easter, the Ascension on the fortieth day, Pentecost).
 *
 * The offices are read from the REAL shipped Novus-Ordo temporal data, which now carries the
 * cited paschal-half archetypes. Dates are computed by hand from the Easter anchor and
 * cross-read against the published 2024–2025 calendars; the full-year oracle sweep is #260.
 */
final class PaschalCycleTest extends TestCase
{
    private const EDITION = 'roman-novus-ordo-2002';

    /** The paschal cycle of the civil year Easter falls in. */
    private function cycle(int $year): PaschalCycle
    {
        return PaschalCycle::forYear($year, Corpus::default(), self::EDITION);
    }

    private static function utc(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
    }

    /** Ash Wednesday opens Lent — a violet feria under its ancient name. Easter 2024 = 31 Mar. */
    public function testAshWednesdayOpensLent(): void
    {
        $ashWednesday = $this->cycle(2024)->on(self::utc('2024-02-14'));

        self::assertNotNull($ashWednesday);
        self::assertSame('roman:temporale:paschal:lent:ash-wednesday', $ashWednesday->id()->toString());
        self::assertSame('Feria IV Cinerum', $ashWednesday->latinName());
        self::assertSame('feria', $ashWednesday->kind()->value());
        self::assertSame(Season::LENT, $ashWednesday->season()->value());
        self::assertSame('violet', $ashWednesday->colour()->base()->value());
    }

    /** Thursday–Saturday after Ash Wednesday are the "post Cineres" ferias. */
    public function testPostCineresFerias(): void
    {
        $thursday = $this->cycle(2024)->on(self::utc('2024-02-15'));

        self::assertSame('roman:temporale:paschal:lent:post-cineres:feria-5', $thursday->id()->toString());
        self::assertSame('Feria V post Cineres', $thursday->latinName());
        self::assertSame('violet', $thursday->colour()->base()->value());
    }

    /** The five Sundays "in Quadragesima"; Laetare (the fourth) permits rose. */
    public function testLentSundays(): void
    {
        $cycle = $this->cycle(2024);

        $first = $cycle->on(self::utc('2024-02-18'));
        self::assertSame('roman:temporale:paschal:lent:sunday-1', $first->id()->toString());
        self::assertSame('Dominica I in Quadragesima', $first->latinName());
        self::assertSame('violet', $first->colour()->base()->value());
        self::assertFalse($first->colour()->roseAllowed());

        $laetare = $cycle->on(self::utc('2024-03-10'));
        self::assertSame('roman:temporale:paschal:lent:sunday-4', $laetare->id()->toString());
        self::assertSame('Dominica IV in Quadragesima', $laetare->latinName());
        self::assertTrue($laetare->colour()->roseAllowed(), 'Laetare permits rose');

        self::assertSame('Dominica V in Quadragesima', $cycle->on(self::utc('2024-03-17'))->latinName());
    }

    /** The ordinary weekdays of Lent — the reform re-words them "… Hebdomadae … Quadragesimae". */
    public function testLentWeekdays(): void
    {
        $monday = $this->cycle(2024)->on(self::utc('2024-02-19')); // Monday of week 1

        self::assertSame('roman:temporale:paschal:lent:week-1:feria-2', $monday->id()->toString());
        self::assertSame('Feria II Hebdomadae I Quadragesimae', $monday->latinName());
        self::assertSame('feria', $monday->kind()->value());
        self::assertSame('violet', $monday->colour()->base()->value());
    }

    /** Palm Sunday — the merged "Dominica in Palmis de Passione Domini", in red. */
    public function testPalmSunday(): void
    {
        $palmSunday = $this->cycle(2024)->on(self::utc('2024-03-24'));

        self::assertSame('roman:temporale:paschal:holy-week:palm-sunday', $palmSunday->id()->toString());
        self::assertSame('Dominica in Palmis de Passione Domini', $palmSunday->latinName());
        self::assertSame('sunday', $palmSunday->kind()->value());
        self::assertSame('red', $palmSunday->colour()->base()->value());
        self::assertSame(Season::LENT, $palmSunday->season()->value());
    }

    /** Monday–Wednesday of Holy Week — the "Hebdomadae Sanctae" weekdays. */
    public function testHolyWeekWeekdays(): void
    {
        $monday = $this->cycle(2024)->on(self::utc('2024-03-25'));

        self::assertSame('roman:temporale:paschal:holy-week:feria-2', $monday->id()->toString());
        self::assertSame('Feria II Hebdomadae Sanctae', $monday->latinName());
        self::assertSame('violet', $monday->colour()->base()->value());
    }

    /**
     * The Sacred Triduum — Holy Thursday reuses the shared base office ("Feria V in Cena Domini",
     * white), Good Friday is red and drops "et Morte", Holy Saturday is white for the Vigil. All
     * three are kind=feria and reported by {@see PaschalCycle::isTriduum()}.
     */
    public function testTriduum(): void
    {
        $cycle = $this->cycle(2024);

        $maundy = $cycle->on(self::utc('2024-03-28'));
        self::assertSame('roman:temporale:paschal:holy-week:maundy-thursday', $maundy->id()->toString());
        self::assertSame('Feria V in Cena Domini', $maundy->latinName());
        self::assertSame('feria', $maundy->kind()->value());
        self::assertSame('white', $maundy->colour()->base()->value());

        $goodFriday = $cycle->on(self::utc('2024-03-29'));
        self::assertSame('roman:temporale:paschal:holy-week:good-friday', $goodFriday->id()->toString());
        self::assertSame('Feria VI in Passione Domini', $goodFriday->latinName());
        self::assertSame('red', $goodFriday->colour()->base()->value());

        $holySaturday = $cycle->on(self::utc('2024-03-30'));
        self::assertSame('roman:temporale:paschal:holy-week:holy-saturday', $holySaturday->id()->toString());
        self::assertSame('Sabbato Sancto', $holySaturday->latinName());
        self::assertSame('white', $holySaturday->colour()->base()->value());

        foreach (['2024-03-28', '2024-03-29', '2024-03-30'] as $day) {
            self::assertTrue($cycle->isTriduum(self::utc($day)), "$day is in the Triduum");
        }
        self::assertFalse($cycle->isTriduum(self::utc('2024-03-27')), 'Wednesday of Holy Week is not the Triduum');
        self::assertFalse($cycle->isTriduum(self::utc('2024-03-31')), 'Easter Sunday is not the Triduum');
    }

    /** Easter Sunday — the reform's re-titled "Dominica Paschae in Resurrectione Domini", white. */
    public function testEasterSunday(): void
    {
        $easter = $this->cycle(2024)->on(self::utc('2024-03-31'));

        self::assertSame('roman:temporale:paschal:easter', $easter->id()->toString());
        self::assertSame('Dominica Paschae in Resurrectione Domini', $easter->latinName());
        self::assertSame('sunday', $easter->kind()->value());
        self::assertSame('white', $easter->colour()->base()->value());
        self::assertSame(Season::EASTERTIDE, $easter->season()->value());
    }

    /** The Octave of Easter — the weekdays "infra octavam Paschae", each a Solemnity of the Lord. */
    public function testEasterOctave(): void
    {
        $cycle = $this->cycle(2024);

        $monday = $cycle->on(self::utc('2024-04-01'));
        self::assertSame('roman:temporale:paschal:easter-octave:feria-2', $monday->id()->toString());
        self::assertSame('Feria II infra octavam Paschae', $monday->latinName());
        self::assertSame('within-octave', $monday->kind()->value());
        self::assertSame('white', $monday->colour()->base()->value());

        $saturday = $cycle->on(self::utc('2024-04-06'));
        self::assertSame('roman:temporale:paschal:easter-octave:sabbatum', $saturday->id()->toString());
        self::assertSame('Sabbato infra octavam Paschae', $saturday->latinName());
    }

    /** The Second Sunday of Easter "de divina Misericordia" closes the octave. */
    public function testDivineMercySunday(): void
    {
        $mercy = $this->cycle(2024)->on(self::utc('2024-04-07'));

        self::assertSame('roman:temporale:paschal:easter:sunday-2', $mercy->id()->toString());
        self::assertSame('Dominica II Paschae seu de divina Misericordia', $mercy->latinName());
        self::assertSame('sunday', $mercy->kind()->value());
        self::assertSame('white', $mercy->colour()->base()->value());
    }

    /** The Sundays III–VII of Easter carry the plain "Dominica … Paschae" name. */
    public function testEasterSundaysThreeToSeven(): void
    {
        $cycle = $this->cycle(2024);

        $third = $cycle->on(self::utc('2024-04-14'));
        self::assertSame('roman:temporale:paschal:easter:sunday-3', $third->id()->toString());
        self::assertSame('Dominica III Paschae', $third->latinName());

        // The Seventh Sunday of Easter (Easter+42) exists in the universal calendar, where the
        // Ascension keeps its Thursday.
        $seventh = $cycle->on(self::utc('2024-05-12'));
        self::assertSame('roman:temporale:paschal:easter:sunday-7', $seventh->id()->toString());
        self::assertSame('Dominica VII Paschae', $seventh->latinName());
    }

    /** The ordinary weekdays of Easter Time — "… Hebdomadae … Paschae", white, the floor rank. */
    public function testEasterWeekdays(): void
    {
        $monday = $this->cycle(2024)->on(self::utc('2024-04-08')); // Monday of week 2

        self::assertSame('roman:temporale:paschal:easter:week-2:feria-2', $monday->id()->toString());
        self::assertSame('Feria II Hebdomadae II Paschae', $monday->latinName());
        self::assertSame('feria', $monday->kind()->value());
        self::assertSame('white', $monday->colour()->base()->value());
    }

    /** The Ascension — the fortieth day (Thursday) in the universal calendar, a shared feast of the Lord. */
    public function testAscension(): void
    {
        $cycle = $this->cycle(2024);

        self::assertSame('2024-05-09', $cycle->ascension()->format('Y-m-d'));
        $ascension = $cycle->on(self::utc('2024-05-09'));
        self::assertSame('roman:temporale:paschal:ascension', $ascension->id()->toString());
        self::assertSame('In Ascensione Domini', $ascension->latinName());
        self::assertSame('feast', $ascension->kind()->value());
        self::assertSame('white', $ascension->colour()->base()->value());
        self::assertSame('Thu', $cycle->ascension()->format('D'));
    }

    /** Pentecost closes Easter Time — red, the last day of the paschal window. */
    public function testPentecost(): void
    {
        $cycle = $this->cycle(2024);

        self::assertSame('2024-05-19', $cycle->pentecost()->format('Y-m-d'));
        $pentecost = $cycle->on(self::utc('2024-05-19'));
        self::assertSame('roman:temporale:paschal:pentecost', $pentecost->id()->toString());
        self::assertSame('Dominica Pentecostes', $pentecost->latinName());
        self::assertSame('red', $pentecost->colour()->base()->value());
        self::assertSame(Season::EASTERTIDE, $pentecost->season()->value());
    }

    /** Ash Wednesday is the first filled day; Pentecost the last. Ordinary Time owns either side. */
    public function testBoundaries(): void
    {
        $cycle = $this->cycle(2024);

        self::assertNotNull($cycle->on(self::utc('2024-02-14')), 'Ash Wednesday is the first filled day');
        self::assertNull($cycle->on(self::utc('2024-02-13')), 'the day before Ash Wednesday is Ordinary Time');
        self::assertNotNull($cycle->on(self::utc('2024-05-19')), 'Pentecost is the last filled day');
        self::assertNull($cycle->on(self::utc('2024-05-20')), 'the Monday after Pentecost is Ordinary Time');
    }

    /**
     * The paschal window is filled contiguously: every day from Ash Wednesday to Pentecost
     * inclusive has an office, and nothing outside it does. Walked for a year whose Easter is
     * late (2025, 20 April) to exercise a different span.
     */
    public function testFillsThePaschalWindowContiguously(): void
    {
        $cycle = $this->cycle(2025);
        $ashWednesday = $cycle->ashWednesday();
        $pentecost = $cycle->pentecost();

        self::assertNull($cycle->on(TemporalCalendar::addDays($ashWednesday, -1)));
        for ($date = $ashWednesday; $date <= $pentecost; $date = TemporalCalendar::addDays($date, 1)) {
            self::assertNotNull($cycle->on($date), $date->format('Y-m-d') . ' is inside the paschal window');
        }
        self::assertNull($cycle->on(TemporalCalendar::addDays($pentecost, 1)));
    }

    /**
     * The complete tiling: with the paschal half in place, the four Novus-Ordo temporal fillers —
     * the previous Advent's Christmas cycle, this year's Ordinary Time, this year's paschal half,
     * and this year's Advent — own EVERY day of a civil year exactly once, with no gap and no
     * overlap. This is the whole reformed Proper of Time proven closed.
     */
    public function testFourFillersTileTheWholeCivilYearExactlyOnce(): void
    {
        $year = 2025;
        $fillers = [
            ChristmasCycle::forYear($year - 1, Corpus::default(), self::EDITION),
            OrdinaryTime::forYear($year, Corpus::default(), self::EDITION),
            PaschalCycle::forYear($year, Corpus::default(), self::EDITION),
            ChristmasCycle::forYear($year, Corpus::default(), self::EDITION),
        ];

        $date = TemporalCalendar::utcDate($year, 1, 1);
        $end = TemporalCalendar::utcDate($year, 12, 31);
        for (; $date <= $end; $date = TemporalCalendar::addDays($date, 1)) {
            $owners = 0;
            foreach ($fillers as $filler) {
                if ($filler->on($date) !== null) {
                    $owners++;
                }
            }
            self::assertSame(
                1,
                $owners,
                sprintf('%s should be owned by exactly one filler, was %d', $date->format('Y-m-d'), $owners)
            );
        }
    }

    public function testRejectsPreGregorianYear(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PaschalCycle::forYear(1580, Corpus::default(), self::EDITION);
    }
}
