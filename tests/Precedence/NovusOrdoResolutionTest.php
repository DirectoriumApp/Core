<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Precedence;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Precedence\ResolvedYear;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end resolution of the Novus-Ordo (2002) General Roman Calendar over the real corpus
 * (#108): the reformed sanctoral, its Table of Liturgical Days, the electable-optional-memorial
 * rule, and the reformed penitential discipline. It reaches the engine through
 * {@see DayResolver::forEdition()} to pin the resolved calendar directly; the edition is now
 * built and also resolvable at the public {@see \Directorium\Core\Overlay\CalendarCatalog}
 * boundary (#260 flipped isBuilt — see {@see \Directorium\Core\Tests\Overlay\CalendarCatalogTest}).
 *
 * 2025 is the pinned year: Easter is 20 April (Ash Wednesday 5 March, Good Friday 18 April), and
 * every Sunday of November falls on an even-numbered date (2/9/16/23/30), so the "outranks a Sunday"
 * cases land on real Sundays.
 */
final class NovusOrdoResolutionTest extends TestCase
{
    private static function resolve(int $year): ResolvedYear
    {
        return DayResolver::forEdition(RubricSystem::fromString('novus-ordo'))->resolveYear($year);
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }

    private static function celebrationId(LiturgicalDay $day): string
    {
        self::assertCount(1, $day->celebration());

        return $day->celebration()[0]->id()->toString();
    }

    /** @param list<RealizedObservance> $offices */
    private static function containsId(array $offices, string $id): bool
    {
        foreach ($offices as $office) {
            if ($office->id()->toString() === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every realized office of the day across all four roles (unwrapped from their roles).
     *
     * @return list<RealizedObservance>
     */
    private static function allObservances(LiturgicalDay $day): array
    {
        return array_merge($day->celebration(), $day->commemoration(), $day->displaced(), $day->tempora());
    }

    public function testAnObligatoryMemorialIsCelebratedOverTheFeria(): void
    {
        // 28 Aug: St Augustine, a saint the reform keeps from 1962 (referenced by id), celebrated as
        // an obligatory memorial over the Ordinary-Time weekday.
        $day = self::resolve(2025)->day(self::utc('2025-08-28'));

        self::assertSame('roman:sanctorale:augustinus', self::celebrationId($day));
        self::assertSame(3, $day->celebration()[0]->rank()->ordinal(), 'a memorial derives RankClass 3');
    }

    public function testAReformMovedSaintResolvesOnItsReformedDate(): void
    {
        // St Thomas Aquinas, moved from 7 March (1962) to 28 January (the reform), same id.
        $day = self::resolve(2025)->day(self::utc('2025-01-28'));

        self::assertSame('roman:sanctorale:thomas-aquinas', self::celebrationId($day));
    }

    public function testAnOptionalMemorialNeverDisplacesTheFeria(): void
    {
        // 20 Jan 2025 is a Monday of Ordinary Time; Fabian and Sebastian are BOTH optional
        // memorials. The day resolves to the feria — the electable options do not displace it and
        // are not celebrated, commemorated, or displaced — but they are surfaced (electable, not
        // celebrated) in the day's optionalMemorials (#260).
        $day = self::resolve(2025)->day(self::utc('2025-01-20'));

        self::assertSame('feria', $day->celebration()[0]->kind()->value());
        // Neither optional memorial appears in the four resolved roles — they are electable.
        $offices = self::allObservances($day);
        self::assertFalse(self::containsId($offices, 'roman:sanctorale:fabianus'));
        self::assertFalse(self::containsId($offices, 'roman:sanctorale:sebastianus'));
        // But both are offered as optional memorials of the day.
        $optional = [];
        foreach ($day->optionalMemorials() as $observance) {
            $optional[] = $observance->id()->toString();
        }
        self::assertContains('roman:sanctorale:fabianus', $optional);
        self::assertContains('roman:sanctorale:sebastianus', $optional);
    }

    public function testASolemnityIsCelebrated(): void
    {
        // 15 Aug: the Assumption, a solemnity.
        $day = self::resolve(2025)->day(self::utc('2025-08-15'));

        self::assertSame('roman:sanctorale:assumptio', self::celebrationId($day));
        self::assertSame(1, $day->celebration()[0]->rank()->ordinal(), 'a solemnity derives RankClass 1');
    }

    public function testAFeastOfTheLordOutranksASunday(): void
    {
        // 9 Nov 2025 is a Sunday; the Dedication of the Lateran Basilica (a Feast of the Lord, line
        // 5) is celebrated over the Sunday of Ordinary Time (line 6).
        $day = self::resolve(2025)->day(self::utc('2025-11-09'));

        self::assertSame('roman:sanctorale:dedicatio-basilicae-salvatoris', self::celebrationId($day));
        self::assertSame('sunday', $day->tempora()[0]->kind()->value(), 'the OT Sunday is the temporal backdrop');
    }

    public function testAReformReTitledFeastOfTheLordOutranksASunday(): void
    {
        // 2 Feb 2025 is a Sunday; the Presentation of the Lord — the reform's re-titling of the
        // 1962 Purification, so a NO-only identity with its own id — is a Feast of the Lord (line 5)
        // and is celebrated over the Sunday of Ordinary Time (line 6).
        $day = self::resolve(2025)->day(self::utc('2025-02-02'));

        self::assertSame('roman:sanctorale:praesentatio-domini', self::celebrationId($day));
        self::assertSame('sunday', $day->tempora()[0]->kind()->value(), 'the OT Sunday is the temporal backdrop');
    }

    public function testAllSoulsIsCelebratedOnASunday(): void
    {
        // 2 Nov 2025 is a Sunday; All Souls (line 3) displaces the Sunday of Ordinary Time — the
        // reformed calendar keeps All Souls on the Sunday (officeOfTheDeadYieldsToSunday() is false).
        $day = self::resolve(2025)->day(self::utc('2025-11-02'));

        self::assertSame('roman:sanctorale:omnium-fidelium-defunctorum', self::celebrationId($day));
    }

    public function testASaintsFeastYieldsToAPrivilegedSunday(): void
    {
        // 30 Nov 2025 is the First Sunday of Advent; St Andrew (a feast of an apostle, line 7) yields
        // to the Advent Sunday (line 2) and is omitted — a feast is not transferred, only a solemnity.
        $day = self::resolve(2025)->day(self::utc('2025-11-30'));

        self::assertSame('roman:temporale:advent:sunday-1', self::celebrationId($day));
        // The reform keeps no commemoration — the impeded feast simply lapses.
        self::assertFalse(self::containsId($day->commemoration(), 'roman:sanctorale:andreas'));
    }

    public function testTheReformedFastIsAshWednesdayAndGoodFridayOnly(): void
    {
        $year = self::resolve(2025);

        $ashWednesday = $year->day(self::utc('2025-03-05'))->fasting();
        self::assertNotNull($ashWednesday);
        self::assertTrue($ashWednesday->fast(), 'Ash Wednesday is a day of fast');
        self::assertSame('full', $ashWednesday->abstinence()->value());
        self::assertSame('ash-wednesday', $ashWednesday->reason());
        self::assertSame('roman:cic-1983', $ashWednesday->disciplineUrn());

        $goodFriday = $year->day(self::utc('2025-04-18'))->fasting();
        self::assertNotNull($goodFriday);
        self::assertTrue($goodFriday->fast(), 'Good Friday is a day of fast');
        self::assertSame('full', $goodFriday->abstinence()->value());
        self::assertSame('good-friday', $goodFriday->reason());

        // Every Friday of the year is abstinence, but not a fast (10 Jan 2025 is a Friday).
        $ordinaryFriday = $year->day(self::utc('2025-01-10'))->fasting();
        self::assertNotNull($ordinaryFriday);
        self::assertFalse($ordinaryFriday->fast(), 'an ordinary Friday is abstinence only, no fast');
        self::assertSame('full', $ordinaryFriday->abstinence()->value());

        // An ordinary weekday of Lent carries NO obligation in the reform (10 March 2025 is a Monday
        // of Lent) — the Lenten-weekday fast of the old discipline is gone.
        self::assertNull($year->day(self::utc('2025-03-10'))->fasting(), 'a Lenten weekday lays no reformed fast');
    }
}
