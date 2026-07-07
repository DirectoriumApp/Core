<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Edition;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Attribute\LegacyRank;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Overlay\CalendarCatalog;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Precedence\ResolvedYear;
use Directorium\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end resolution under the 1955 interim (Cum nostra hac aetate) engine (#70): the
 * reduced precedence rules wired into {@see DayResolver}, run over the real generator-derived
 * 1955 corpus. These prove the reform's calendar-visible outcomes — the two-step grade
 * reduction, the suppressed vigils, the vigil no longer anticipated onto a Saturday, and the
 * privileged commemoration kept over a first-class day's zero cap — and that the whole year
 * resolves cleanly.
 *
 * Like the 1954 engine, 1955 is exercised through {@see DayResolver::forEdition()}; with the
 * #453 burndown complete (the retained temporal octaves, the moveable feasts of the Lord and
 * BVM, the Office of the Dead, the Saturday Office of Our Lady all built), it is now advertised
 * as built and also resolves through the public {@see CalendarCatalog} boundary. The day-by-day
 * cross-check against the Divinum Officium "Reduced - 1955" engine is issue #71.
 */
final class CumNostraResolutionTest extends TestCase
{
    private static function resolver(): DayResolver
    {
        return DayResolver::forEdition(RubricSystem::rubricae1955());
    }

    private static function on(ResolvedYear $year, string $date): LiturgicalDay
    {
        return $year->day(new DateTimeImmutable($date, new DateTimeZone('UTC')));
    }

    private static function celebrationId(LiturgicalDay $day): ?string
    {
        $celebration = $day->celebration();

        return $celebration === [] ? null : $celebration[0]->id()->toString();
    }

    private static function celebrationLegacyRank(LiturgicalDay $day): ?string
    {
        $celebration = $day->celebration();
        if ($celebration === []) {
            return null;
        }
        $observance = $celebration[0];
        if (!$observance instanceof SanctoralObservance) {
            return null;
        }
        $legacyRank = $observance->legacyRank();

        return $legacyRank !== null ? $legacyRank->value() : null;
    }

    /** @return list<string> */
    private static function commemorationIds(LiturgicalDay $day): array
    {
        return array_map(static fn ($office) => $office->id()->toString(), $day->commemoration());
    }

    private static function hasOffice(LiturgicalDay $day, string $id): bool
    {
        foreach ($day->offices() as $office) {
            if ($office->observance()->id()->toString() === $id) {
                return true;
            }
        }

        return false;
    }

    public function testEveryRepresentativeYearResolvesWithoutError(): void
    {
        $resolver = self::resolver();
        foreach ([1956, 1957, 1958, 1959, 1960] as $year) {
            $resolved = $resolver->resolveYear($year);
            self::assertSame(
                'roman:temporale:christmas:nativity',
                self::celebrationId(self::on($resolved, $year . '-12-25')),
                'Christmas is celebrated in ' . $year
            );
        }
    }

    public function testAFormerSemidoubleIsCelebratedAsASimpleOffice(): void
    {
        // St Alexius (17 Jul) was a semidouble under Divino Afflatu; Cum nostra (Title II.20)
        // keeps him as a SIMPLE. He is still the office of an otherwise-free green feria, now at
        // the reduced grade — the derived 1955 datum carries `simplex`. (17 Jul 1956 is a
        // weekday, so no Sunday intervenes.)
        $day = self::on(self::resolver()->resolveYear(1956), '1956-07-17');

        self::assertSame('roman:sanctorale:alexius', self::celebrationId($day), 'Alexius is the office of the day');
        self::assertSame(LegacyRank::SIMPLEX, self::celebrationLegacyRank($day), 'at the reduced simple grade');
    }

    public function testASuppressedVigilIsAbsentButARetainedVigilIsKept(): void
    {
        // Cum nostra (Title II.8-9) cut the calendar to seven vigils. The Vigil of St Matthias
        // is gone; the Vigil of the Assumption survives. (14 Aug 1956 is a Tuesday, so the
        // retained vigil sits on its own date.)
        $year = self::resolver()->resolveYear(1956);

        self::assertFalse(
            self::hasOffice(self::on($year, '1956-02-23'), 'roman:sanctorale:matthias:vigilia'),
            'the suppressed Matthias vigil is absent'
        );
        self::assertTrue(
            self::hasOffice(self::on($year, '1956-08-14'), 'roman:sanctorale:assumptio:vigilia'),
            'the retained Assumption vigil is kept'
        );
    }

    public function testACommonVigilFallingOnASundayIsNotAnticipatedToSaturday(): void
    {
        // 14 Aug 1960 (the Vigil of the Assumption) is a Sunday. Unlike the pre-1955 rite, Cum
        // nostra (Title II.10) does NOT anticipate the common vigil to the preceding Saturday —
        // 13 Aug is free of it (the reform 1962 also keeps).
        $year = self::resolver()->resolveYear(1960);

        self::assertFalse(
            self::hasOffice(self::on($year, '1960-08-13'), 'roman:sanctorale:assumptio:vigilia'),
            'the vigil is not anticipated onto Saturday 13 Aug'
        );
        // Positive counterpart, so the test cannot pass vacuously if the vigil vanished: it stays
        // on its own date (the Sunday), commemorated under the Sunday office rather than moved.
        self::assertTrue(
            self::hasOffice(self::on($year, '1960-08-14'), 'roman:sanctorale:assumptio:vigilia'),
            'the vigil stays on its own date (the Sunday 14 Aug), not anticipated away'
        );
    }

    public function testAFirstClassFeastCommemoratesAPrivilegedLentenFeria(): void
    {
        // 19 Mar 1958 is a Lenten weekday. St Joseph (a Double of the I class) is the office of
        // the day; its additional-commemoration count is zero (Title III.4a), yet the Lenten
        // feria — a never-omitted privileged commemoration (Title III.2c) — is still kept. This
        // is the reform's privileged-over-the-cap rule, end to end.
        $day = self::on(self::resolver()->resolveYear(1958), '1958-03-19');

        self::assertSame('roman:sanctorale:ioseph', self::celebrationId($day), 'St Joseph is celebrated');
        $lentenFeria = array_filter(
            self::commemorationIds($day),
            static fn (string $id) => strpos($id, 'roman:temporale:paschal:lent-week') === 0
        );
        self::assertNotEmpty($lentenFeria, 'the privileged Lenten feria is commemorated over the zero cap');
    }

    public function testThePublicBoundaryNowResolvesTheInterimEdition(): void
    {
        // Since #453 the interim calendar is advertised as built, so the public CalendarCatalog
        // boundary resolves it — where it once refused. The Assumption (15 Aug, first class) is a
        // stable probe that a real calendar comes back.
        $day = (new CalendarCatalog())->resolver(null, '1955')
            ->resolveDay(new DateTimeImmutable('1958-08-15', new DateTimeZone('UTC')));

        self::assertSame('roman:sanctorale:assumptio', $day->celebration()[0]->id()->toString());
    }
}
