<?php

declare(strict_types=1);

namespace Directorium\Core\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

use function Directorium\Core\contract;
use function Directorium\Core\day;

final class DayFunctionTest extends TestCase
{
    public function testDayResolvesTheCelebrationForADate(): void
    {
        $day = day(new DateTimeImmutable('2025-12-25', new DateTimeZone('UTC')));

        self::assertFalse($day->isEmpty());
        self::assertCount(1, $day->celebration());
        self::assertSame(
            'roman:temporale:christmas:nativity',
            $day->celebration()[0]->id()->toString()
        );
    }

    public function testDayIsMemoisedAndStableAcrossCalls(): void
    {
        $first = day(new DateTimeImmutable('2025-06-29', new DateTimeZone('UTC')));
        $second = day(new DateTimeImmutable('2025-06-29', new DateTimeZone('UTC')));

        self::assertSame(
            $first->celebration()[0]->id()->toString(),
            $second->celebration()[0]->id()->toString()
        );
        self::assertSame('roman:sanctorale:petrus-paulus', $first->celebration()[0]->id()->toString());
    }

    public function testDaySelectsAParticularCalendar(): void
    {
        $date = new DateTimeImmutable('2026-09-03', new DateTimeZone('UTC'));

        $universal = day($date);
        $sspx = day($date, 'sspx');

        // Same feast, elevated to first class under the SSPX particular calendar.
        self::assertSame('roman:sanctorale:pius-x', $universal->celebration()[0]->id()->toString());
        self::assertSame(3, $universal->celebration()[0]->rank()->ordinal());
        self::assertSame('roman:sanctorale:pius-x', $sspx->celebration()[0]->id()->toString());
        self::assertSame(1, $sspx->celebration()[0]->rank()->ordinal());
    }

    public function testContractStampsTheSelectedCalendarBlock(): void
    {
        $date = new DateTimeImmutable('2026-09-03', new DateTimeZone('UTC'));

        // The astronomical block (#242) is present on every day, edition-invariant.
        $astronomical = [
            'goldenNumber' => 13,
            'epact' => 11,
            'solarCycle' => 19,
            'dominicalLetter' => 'D',
            'romanIndiction' => 4,
            'lunarAge' => 20,
        ];

        // Under the universal calendar the block carries astronomical only — no particular.
        self::assertSame(['astronomical' => $astronomical], contract($date)['calendar']);

        $sspx = contract($date, false, 'sspx');
        self::assertSame(
            [
                'particular' => ['id' => 'directorium:overlay:roman:sspx', 'name' => 'Society of Saint Pius X'],
                'astronomical' => $astronomical,
            ],
            $sspx['calendar']
        );
        self::assertSame(1, $sspx['celebration'][0]['rankOrdinal']);
        // The overlay also travels on the corpus-version axis.
        self::assertStringContainsString('+directorium:overlay:roman:sspx', $sspx['corpusVersion']);
    }

    public function testDayDefaultsToTheNineteenSixtyEdition(): void
    {
        $date = new DateTimeImmutable('2026-06-29', new DateTimeZone('UTC'));
        $default = day($date)->celebration()[0]->id()->toString();

        // Naming the 1962 edition explicitly (by alias or URN) resolves identically to the default.
        self::assertSame($default, day($date, null, '1962')->celebration()[0]->id()->toString());
        self::assertSame($default, day($date, null, 'roman:rubricae-1960')->celebration()[0]->id()->toString());
    }

    public function testContractStampsTheEditionProvenance(): void
    {
        $date = new DateTimeImmutable('2026-06-29', new DateTimeZone('UTC'));

        self::assertSame('roman:rubricae-1960', contract($date)['edition']);
        self::assertSame('roman:rubricae-1960', contract($date, false, null, '1962')['edition']);
    }

    public function testTheHistoricalEditionsResolveAtThePublicBoundary(): void
    {
        // Once #453 flipped their isBuilt flag, 1954 and 1955 resolve through the public day()
        // boundary exactly like 1962 — the guard no longer refuses them. St Peter's Chair at Rome
        // (18 Jan) is a per-annum weekday feast present under every edition, a stable probe.
        $date = new DateTimeImmutable('1954-01-18', new DateTimeZone('UTC'));

        foreach (['1954', 'roman:divino-afflatu', '1955', 'roman:rubricae-1955'] as $selector) {
            $day = day($date, null, $selector);
            self::assertFalse($day->isEmpty(), "$selector resolves a non-empty day");
            self::assertCount(1, $day->celebration(), "$selector celebrates exactly one office");
        }

        // The edition is a real discriminator: naming 1954 stamps its own edition URN, not 1962's.
        self::assertSame('roman:divino-afflatu', contract($date, false, null, '1954')['edition']);
        self::assertSame('roman:rubricae-1955', contract($date, false, null, '1955')['edition']);
    }
}
