<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Precedence;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Calendar\LiturgicalDay;
use Introibo\Core\Calendar\RealizedObservance;
use Introibo\Core\Precedence\DayResolver;
use Introibo\Core\Precedence\ResolvedYear;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end resolution: day() composing the temporal skeleton and the sanctoral
 * overlay into one celebrated office, with commemorations, displacement, and the
 * whole-year transfer sweep.
 */
final class DayResolverTest extends TestCase
{
    public function testChristmasIsCelebrated(): void
    {
        $day = self::resolve(2025)->day(self::utc('2025-12-25'));

        self::assertSame('roman:temporale:christmas:nativity', self::celebrationId($day));
    }

    public function testAnOrdinaryFeriaIsBothTheCelebrationAndTheTempora(): void
    {
        $day = self::resolve(2025)->day(self::utc('2025-07-15'));

        self::assertCount(1, $day->celebration());
        self::assertCount(1, $day->tempora());
        self::assertSame($day->tempora()[0]->id()->toString(), self::celebrationId($day));
        self::assertSame('feria', $day->celebration()[0]->kind()->value());
    }

    public function testAFirstClassFeastOutranksItsLentenFeriaAndCommemoratesIt(): void
    {
        // 19 March 2025 is a Wednesday of Lent; St Joseph (I) is celebrated and
        // the Lenten feria is commemorated.
        $day = self::resolve(2025)->day(self::utc('2025-03-19'));

        self::assertSame('roman:sanctorale:ioseph', self::celebrationId($day));
        self::assertNotEmpty($day->commemoration());
        self::assertSame('feria', $day->tempora()[0]->kind()->value());
    }

    public function testAnOrdinaryFeriaUnderAFeastIsNotCommemorated(): void
    {
        // 24 Feb 2025 (Monday of Sexagesima): St Matthias is celebrated and the
        // ordinary pre-Lent feria is omitted, not commemorated.
        $day = self::resolve(2025)->day(self::utc('2025-02-24'));

        self::assertSame('roman:sanctorale:matthias', self::celebrationId($day));
        self::assertEmpty($day->commemoration());
    }

    public function testImmaculateConceptionIsCelebrated(): void
    {
        $day = self::resolve(2025)->day(self::utc('2025-12-08'));

        self::assertSame('roman:sanctorale:immaculata-conceptio', self::celebrationId($day));
    }

    public function testAChristmasOctaveFeastIsCelebratedWithTheOctaveCommemorated(): void
    {
        // St Stephen (26 Dec, II) is celebrated; the day within the Christmas
        // octave is the tempora and is commemorated.
        $day = self::resolve(2025)->day(self::utc('2025-12-26'));

        self::assertSame('roman:sanctorale:stephanus', self::celebrationId($day));
        self::assertNotEmpty($day->commemoration());
        self::assertSame('within-octave', $day->tempora()[0]->kind()->value());
    }

    /**
     * The whole-year transfer sweep: St Joseph falls on the third Sunday of Lent
     * in 2017, so the Sunday is celebrated (St Joseph displaced) and St Joseph is
     * transferred to and celebrated on the following day.
     */
    public function testAFirstClassFeastImpededByASundayIsTransferredToTheNextDay(): void
    {
        $year = self::resolve(2017);

        $sunday = $year->day(self::utc('2017-03-19'));
        self::assertSame('sunday', $sunday->celebration()[0]->kind()->value());
        self::assertTrue(self::containsId($sunday->displaced(), 'roman:sanctorale:ioseph'));

        $monday = $year->day(self::utc('2017-03-20'));
        self::assertSame('roman:sanctorale:ioseph', self::celebrationId($monday));
    }

    public function testTheEveningConcurrenceIsResolved(): void
    {
        // 25 Dec (Nativity) into 26 Dec (St Stephen): both have celebrations.
        $day = self::resolve(2025)->day(self::utc('2025-12-25'));

        self::assertNotNull($day->secondVespers());
    }

    private static function resolve(int $year): ResolvedYear
    {
        return DayResolver::for1962()->resolveYear($year);
    }

    private static function celebrationId(LiturgicalDay $day): string
    {
        self::assertCount(1, $day->celebration());

        return $day->celebration()[0]->id()->toString();
    }

    /**
     * @param list<RealizedObservance> $offices
     */
    private static function containsId(array $offices, string $id): bool
    {
        foreach ($offices as $office) {
            if ($office->id()->toString() === $id) {
                return true;
            }
        }

        return false;
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
