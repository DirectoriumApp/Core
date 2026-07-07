<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Edition;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Overlay\CalendarCatalog;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Precedence\ResolvedYear;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end resolution under the 1954 (Divino Afflatu) engine (#67): the pre-1955
 * precedence rules wired into {@see DayResolver}, run over the real 1954 corpus. These
 * prove the CONFIRMED dated outcomes the research verified against the St. Lawrence Press
 * pre-1955 Ordo (ordo-1954) — the Candlemas asymmetry, the Matthias bissextile, and the
 * common-vigil Saturday-anticipation — and that the whole year resolves cleanly.
 *
 * The full fixed-date sanctoral now lands (Epic #64) — every feast at its cited pre-1955
 * grade, validated day-by-day across 1954 and 1956 against the Divinum Officium Divino
 * Afflatu engine at zero grade discrepancies. The edition is STILL not advertised as built,
 * because the pre-1955 calendar has components beyond the fixed sanctoral that remain
 * deferred (the privileged temporal octaves, the moveable feasts of the Lord and of the
 * BVM, the Office of the Dead, the Saturday Office of Our Lady). So it is exercised here
 * through {@see DayResolver::forEdition()}, and the public {@see CalendarCatalog} boundary
 * refuses it until those land (see docs/design/rubric-system-model.md).
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

    public function testTheOctaveOfAllSaintsYieldsToAllSoulsAndResumes(): void
    {
        // The octave of All Saints (Nov 1) runs Nov 2-8. On 2 Nov the Office of the Dead holds
        // the day and the day within is omitted (a requiem admits no commemoration); the octave
        // resumes on 3 Nov and closes with its greater-double octave day on 8 Nov (#453).
        $year = self::resolver()->resolveYear(1954);

        self::assertSame(
            'roman:sanctorale:omnium-fidelium-defunctorum',
            self::celebrationId(self::on($year, '1954-11-02'))
        );
        self::assertNotContains(
            'roman:sanctorale:omnes-sancti:infra-octavam:2',
            self::commemorationIds(self::on($year, '1954-11-02')),
            'All Souls admits no commemoration, so the day within is omitted on 2 Nov'
        );
        self::assertSame(
            'roman:sanctorale:omnes-sancti:infra-octavam:3',
            self::celebrationId(self::on($year, '1954-11-03')),
            'the octave resumes on 3 Nov'
        );
        self::assertSame(
            'roman:sanctorale:omnes-sancti:in-octava',
            self::celebrationId(self::on($year, '1954-11-08')),
            'the octave closes with its octave day on 8 Nov'
        );
    }

    public function testTheImmaculateConceptionOctaveIsCommemoratedUnderTheFeastsWithinIt(): void
    {
        // The Immaculate Conception octave (Dec 8) runs Dec 9-15. St Damasus (Dec 11) and St
        // Lucy (Dec 13) are celebrated within it, the octave commemorated; it closes with its
        // greater-double octave day on 15 Dec (#453).
        $year = self::resolver()->resolveYear(1954);

        $damasus = self::on($year, '1954-12-11');
        self::assertSame('roman:sanctorale:damasus', self::celebrationId($damasus));
        self::assertContains(
            'roman:sanctorale:immaculata-conceptio:infra-octavam:4',
            self::commemorationIds($damasus)
        );

        $lucy = self::on($year, '1954-12-13');
        self::assertSame('roman:sanctorale:lucia', self::celebrationId($lucy));
        self::assertContains(
            'roman:sanctorale:immaculata-conceptio:infra-octavam:6',
            self::commemorationIds($lucy)
        );

        self::assertSame(
            'roman:sanctorale:immaculata-conceptio:in-octava',
            self::celebrationId(self::on($year, '1954-12-15'))
        );
    }

    public function testTheComitesChristiKeepTheirSimpleOctaveDays(): void
    {
        // St John (3 Jan) and the Holy Innocents (4 Jan) keep their simple octave days after
        // the Circumcision; St Stephen's octave (2 Jan) yields to the Holy Name when a Sunday
        // falls there (1955) and is commemorated (#453).
        $year = self::resolver()->resolveYear(1955);

        self::assertSame(
            'roman:sanctorale:ioannes-evangelista:in-octava',
            self::celebrationId(self::on($year, '1955-01-03'))
        );
        self::assertSame(
            'roman:sanctorale:innocentes:in-octava',
            self::celebrationId(self::on($year, '1955-01-04'))
        );

        $holyName = self::on($year, '1955-01-02');
        self::assertSame('roman:temporale:christmas:holy-name', self::celebrationId($holyName));
        self::assertContains('roman:sanctorale:stephanus:in-octava', self::commemorationIds($holyName));
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

    public function testASaintReducedToACommemorationInNineteenSixtyIsAFullOfficeInFiftyFour(): void
    {
        // St Blaise (3 Feb) is a COMMEMORATION-ONLY entry in the 1962 base, but under the
        // pre-1955 rubrics he is a Simplex office that IS the day on a free feria. The 1954
        // engine must classify him by his native grade, not floor him by the 1962 kind
        // (verified vs the DA engine; the burndown's largest class of correction).
        $day = self::on(self::resolver()->resolveYear(1954), '1954-02-03');

        self::assertSame('roman:sanctorale:blasius', self::celebrationId($day));
    }

    public function testAFeastMovedByTheReformSitsAtItsPreNineteenFiftyFivePlace(): void
    {
        // Ss. Philip & James are 11 May in 1962 (displaced when 1 May became St Joseph the
        // Worker in 1955), but 1 May under the pre-1955 calendar — a Double of the II class.
        $year = self::resolver()->resolveYear(1954);

        self::assertSame('roman:sanctorale:philippus-iacobus', self::celebrationId(self::on($year, '1954-05-01')));
        self::assertFalse(
            self::hasOffice(self::on($year, '1954-05-11'), 'roman:sanctorale:philippus-iacobus'),
            'the apostles are not at their 1962 date in 1954'
        );
    }

    public function testASuppressedFeastIsCelebratedInFiftyFour(): void
    {
        // Feasts the 1955/1960 reforms abolished are restored for 1954: the Finding of the
        // Holy Cross (3 May, Double II class) and the Apparition of St Michael (8 May, greater
        // double) are the office of their day.
        $year = self::resolver()->resolveYear(1954);

        self::assertSame('roman:sanctorale:inventio-crucis', self::celebrationId(self::on($year, '1954-05-03')));
        self::assertSame('roman:sanctorale:apparitio-michaelis', self::celebrationId(self::on($year, '1954-05-08')));
    }

    public function testAPostNineteenFiftyFourFeastIsAbsentAndItsPredecessorHoldsTheDay(): void
    {
        // St Lawrence of Brindisi's universal feast (21 Jul) postdates the Divino Afflatu
        // period (he was declared a Doctor in 1959), so 1954 keeps S. Praxedis on 21 July.
        $day = self::on(self::resolver()->resolveYear(1954), '1954-07-21');

        self::assertSame('roman:sanctorale:praxedes', self::celebrationId($day));
        $brindisi = 'roman:sanctorale:laurentius-a-brundusio';
        self::assertFalse(self::hasOffice($day, $brindisi), 'Lawrence of Brindisi is absent');
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
