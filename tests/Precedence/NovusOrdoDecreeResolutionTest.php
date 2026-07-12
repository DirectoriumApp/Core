<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Precedence;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end resolution of the two real post-2002 decrees over the Novus-Ordo 2002
 * snapshot (#366): the dated decrees are replayed by their effective date, so the same
 * date always resolves the same way, and the traditional editions — which ship no
 * decrees — are untouched.
 *
 *  - Mary Magdalene (22 July), raised to a FEAST by "Apostolorum Apostola" (2016).
 *  - Blessed Virgin Mary, Mother of the Church (Monday after Pentecost), instituted by
 *    "Ecclesia Mater" (2018): a movable memorial. Pentecost Monday is 5 June 2017,
 *    21 May 2018, 9 June 2025, 25 May 2026.
 */
final class NovusOrdoDecreeResolutionTest extends TestCase
{
    private const MAGDALENE = 'roman:sanctorale:maria-magdalena';
    private const MATER_ECCLESIAE = 'roman:sanctorale:maria-mater-ecclesiae';

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }

    private static function novusOrdo(string $ymd): LiturgicalDay
    {
        return DayResolver::forEdition(RubricSystem::fromString('novus-ordo'))->resolveDay(self::utc($ymd));
    }

    public function testMagdaleneIsAMemorialBeforeTheDecree(): void
    {
        // 22 July 2015 precedes the 3 June 2016 decree, so she keeps her 2002 memorial grade.
        $day = self::novusOrdo('2015-07-22');

        self::assertSame(self::MAGDALENE, $day->celebration()[0]->id()->toString());
        self::assertSame(3, $day->celebration()[0]->rank()->ordinal());
    }

    public function testMagdaleneIsAFeastFromTheDecreeYearOnward(): void
    {
        // The decree is effective 3 June 2016, so 22 July 2016 and every later year is a feast.
        foreach (['2016-07-22', '2025-07-22', '2026-07-22'] as $date) {
            $day = self::novusOrdo($date);
            self::assertSame(self::MAGDALENE, $day->celebration()[0]->id()->toString(), $date);
            self::assertSame(2, $day->celebration()[0]->rank()->ordinal(), "$date is a feast");
        }
    }

    public function testMotherOfTheChurchIsAbsentBeforeTheDecree(): void
    {
        // Pentecost Monday 2017 (5 June) precedes the 11 Feb 2018 decree — no such memorial yet.
        $day = self::novusOrdo('2017-06-05');

        self::assertNotSame(self::MATER_ECCLESIAE, $day->celebration()[0]->id()->toString());
    }

    public function testMotherOfTheChurchIsCelebratedFromTheDecreeYearOnward(): void
    {
        // From 2018 the obligatory memorial is celebrated on the Monday after Pentecost, winning
        // the green Ordinary-Time weekday it falls on.
        foreach (['2018-05-21', '2025-06-09', '2026-05-25'] as $date) {
            $day = self::novusOrdo($date);
            self::assertSame(self::MATER_ECCLESIAE, $day->celebration()[0]->id()->toString(), $date);
            self::assertSame(3, $day->celebration()[0]->rank()->ordinal(), "$date is an obligatory memorial");
            self::assertSame('white', $day->celebration()[0]->colour()->base()->value(), $date);
        }
    }

    public function testTheDecreesDoNotTouchTheTraditionalEditions(): void
    {
        // The traditional editions ship no decrees, so St Mary Magdalene keeps her grade (a
        // third-class double) on 22 July — the decrees are an edition-scoped, additive layer.
        // Each edition is probed inside its own validity window (1954 → 1913–55, 1955 → 56–60).
        foreach (['1962' => 2025, '1954' => 1950, '1955' => 1958] as $edition => $year) {
            $day = DayResolver::forEdition(RubricSystem::fromString((string) $edition))
                ->resolveDay(self::utc($year . '-07-22'));
            self::assertSame(self::MAGDALENE, $day->celebration()[0]->id()->toString(), (string) $edition);
            self::assertSame(3, $day->celebration()[0]->rank()->ordinal(), "$edition keeps her memorial-grade double");
        }
    }
}
