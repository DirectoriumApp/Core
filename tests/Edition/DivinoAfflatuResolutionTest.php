<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Edition;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Calendar\LiturgicalDay;
use Introibo\Core\Edition\RubricSystem;
use Introibo\Core\Overlay\CalendarCatalog;
use Introibo\Core\Precedence\DayResolver;
use Introibo\Core\Precedence\ResolvedYear;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end resolution under the 1954 (Divino Afflatu) engine (#67): the pre-1955
 * precedence rules wired into {@see DayResolver}, run over the real 1954 corpus. These
 * prove the CONFIRMED dated outcomes the research verified against the St. Lawrence Press
 * pre-1955 Ordo (ordo-1954) — the Candlemas asymmetry, the Matthias bissextile, and the
 * common-vigil Saturday-anticipation — and that the whole year resolves cleanly.
 *
 * The dataset is intentionally partial (the full 1954 sanctoral burndown is Epic #64), so
 * the edition is not yet advertised as built: it is exercised here through
 * {@see DayResolver::forEdition()}, and the public {@see CalendarCatalog} boundary refuses
 * it until #64 completes the calendar.
 */
final class DivinoAfflatuResolutionTest extends TestCase
{
    private static function resolver(): DayResolver
    {
        return DayResolver::forEdition(RubricSystem::divinoAfflatu());
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

    private static function wasTransferred(LiturgicalDay $day, string $id): bool
    {
        foreach ($day->offices() as $office) {
            if (
                $office->observance()->id()->toString() === $id
                && $office->outcome() !== null
                && $office->outcome()->isTransfer()
            ) {
                return true;
            }
        }

        return false;
    }

    public function testEveryRepresentativeYearResolvesWithoutError(): void
    {
        $resolver = self::resolver();
        foreach ([1914, 1930, 1948, 1952, 1954] as $year) {
            $resolved = $resolver->resolveYear($year);
            // A day everyone keeps: Christmas is the celebrated office.
            self::assertSame(
                'roman:temporale:christmas:nativity',
                self::celebrationId(self::on($resolved, $year . '-12-25')),
                'Christmas is celebrated in ' . $year
            );
        }
    }

    public function testCandlemasWinsAndCommemoratesAGreenSunday(): void
    {
        // 2 Feb 1930 is the 4th Sunday after the Epiphany (a lesser, per-annum Sunday). The
        // Purification (Double II class) takes the day; the Sunday is commemorated — the
        // Divino Afflatu elevation still yields to a Double II (ordo-1954, T2).
        $day = self::on(self::resolver()->resolveYear(1930), '1930-02-02');

        self::assertSame('roman:sanctorale:purificatio', self::celebrationId($day));
        self::assertContains('roman:temporale:epiphany:sunday-4', self::commemorationIds($day));
    }

    public function testCandlemasCedesToAndIsTransferredByASecondClassSunday(): void
    {
        // 2 Feb 1947 is Septuagesima (a II-class greater Sunday, which yields only to a Double
        // of the I class). The Sunday keeps the day and the Purification is TRANSFERRED, not
        // merely commemorated — the same feast, the opposite outcome, keyed on the Sunday's
        // class (ordo-1954, T1; the Candlemas asymmetry).
        $day = self::on(self::resolver()->resolveYear(1947), '1947-02-02');

        self::assertSame('roman:temporale:paschal:septuagesima', self::celebrationId($day));
        self::assertTrue(self::wasTransferred($day, 'roman:sanctorale:purificatio'), 'the Purification is transferred');
    }

    public function testTheMatthiasVigilShiftsWithItsFeastInALeapYear(): void
    {
        // Common year: the vigil sits at its nominal anchor, 23 Feb. Leap year: the feast
        // moves 24 -> 25 Feb across the doubled bis-sextum, so its vigil moves 23 -> 24 Feb.
        // (1953 is common with 23 Feb a Monday; 1956 is leap with 24 Feb a Friday — both
        // clean of the Sunday-anticipation rule.)
        $vigil = 'roman:sanctorale:matthias:vigilia';

        $common = self::resolver()->resolveYear(1953);
        self::assertTrue(self::hasOffice(self::on($common, '1953-02-23'), $vigil), 'common year: vigil on 23 Feb');

        $leap = self::resolver()->resolveYear(1956);
        self::assertTrue(self::hasOffice(self::on($leap, '1956-02-24'), $vigil), 'leap year: vigil on 24 Feb');
        self::assertFalse(self::hasOffice(self::on($leap, '1956-02-23'), $vigil), 'leap year: vigil not on 23 Feb');
    }

    public function testACommonVigilFallingOnASundayIsAnticipatedToSaturday(): void
    {
        // 14 Aug 1955 (the Vigil of the Assumption) is a Sunday, so the common vigil is
        // anticipated to the preceding Saturday, 13 Aug (ordo-1954, T6). The Sunday itself is
        // free of the vigil.
        $vigil = 'roman:sanctorale:assumptio:vigilia';
        $year = self::resolver()->resolveYear(1955);

        self::assertTrue(self::hasOffice(self::on($year, '1955-08-13'), $vigil), 'anticipated to Saturday 13 Aug');
        self::assertFalse(self::hasOffice(self::on($year, '1955-08-14'), $vigil), 'not on the Sunday 14 Aug');
    }

    /**
     * @dataProvider unbuiltEditions
     */
    public function testThePublicBoundaryRefusesAnUnbuiltEdition(string $selector): void
    {
        // 1954 is declared but not yet built (its calendar is incomplete pending #64) and 1955
        // has no engine at all (#68), so the public resolver refuses both — even though the
        // 1954 engine above resolves it directly through forEdition().
        $this->expectException(InvalidArgumentException::class);
        (new CalendarCatalog())->resolver(null, $selector);
    }

    /**
     * @return array<string, array{string}>
     */
    public function unbuiltEditions(): array
    {
        return ['1954 (Divino Afflatu)' => ['1954'], '1955 (interim)' => ['1955']];
    }
}
