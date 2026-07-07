<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Edition;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Precedence\ResolvedYear;
use PHPUnit\Framework\TestCase;

/**
 * The votive Office of Our Lady on a free Saturday — the Officium Sanctae Mariae in Sabbato
 * (#453) — resolved end-to-end. {@see \Directorium\Core\Temporal\SaturdayOfOurLady} mints it on
 * every eligible Saturday of the 1954 (Divino Afflatu) and 1955 (Cum nostra) editions, gated on
 * the edition ({@see \Directorium\Core\Temporal\TemporalAttributes::has}); the per-day contest
 * with a coincident feast / feria / vigil is resolved by the precedence engine.
 *
 * The signature outcomes (docs/design/rubric-system-model.md, Seam 9): it is the Office of the
 * day on a free Saturday (a coincident Simple commemorated under it); it YIELDS — dropped, not
 * commemorated — to any Semidouble-or-higher and to a privileged feria / vigil / Ember day; it is
 * NOT said in Advent, the Christmas → Epiphany block, or Lent/Passiontide (but IS said in pre-Lent);
 * and 1955 keeps it (with MORE free Saturdays than 1954, the collapsed octaves freeing them). All
 * dates are by computus; 1962 never mints it, so the base calendar is unmoved.
 */
final class SaturdayOfOurLadyResolutionTest extends TestCase
{
    private const LADY = 'roman:sanctorale:sancta-maria-sabbato';

    private static function daYear(int $year): ResolvedYear
    {
        return DayResolver::forEdition(RubricSystem::divinoAfflatu())->resolveYear($year);
    }

    private static function cnYear(int $year): ResolvedYear
    {
        return DayResolver::forEdition(RubricSystem::rubricae1955())->resolveYear($year);
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

    /** The celebration role of an office on the day (celebration / commemoration / displaced), or null. */
    private static function roleOf(LiturgicalDay $day, string $id): ?string
    {
        foreach ($day->offices() as $office) {
            if ($office->observance()->id()->toString() === $id) {
                return $office->role()->value();
            }
        }

        return null;
    }

    // --- The Office is the day on a free Saturday ----------------------------------------

    public function testTheOfficeIsCelebratedOnAFreeSaturdayInTimeAfterTheEpiphany(): void
    {
        // 13 Feb 1954, a Saturday in the time after the Epiphany with only the ordinary feria: the
        // Office of Our Lady is the Office of the day, white; the feria yields with no commemoration.
        $day = self::on(self::daYear(1954), '1954-02-13');

        self::assertSame(self::LADY, self::celebrationId($day));
        self::assertSame([], $day->commemoration(), 'A free green Saturday commemorates nothing.');
    }

    public function testTheOfficeIsSaidOnAPreLentSaturdayNotLumpedWithLent(): void
    {
        // 20 Feb 1954 falls in Septuagesima (Easter 18 Apr → Septuagesima 14 Feb): a pre-Lenten
        // Saturday is NOT excluded (its feria is ordinary, not privileged), so the Office is said.
        self::assertSame(self::LADY, self::celebrationId(self::on(self::daYear(1954), '1954-02-20')));
    }

    public function testTheOfficeIsSaidOnAPaschaltideSaturday(): void
    {
        // 22 May 1954, a free Saturday between the Easter octave and Ascension.
        self::assertSame(self::LADY, self::celebrationId(self::on(self::daYear(1954), '1954-05-22')));
    }

    public function testACoincidentSimpleIsCommemoratedUnderTheOffice(): void
    {
        // 18 Feb 1950, a free Saturday carrying the Simple of St Simeon: the votive Office of the
        // day OUTRANKS the Simple, so the Office is celebrated and the Simple commemorated.
        $day = self::on(self::daYear(1950), '1950-02-18');

        self::assertSame(self::LADY, self::celebrationId($day));
        self::assertSame('commemoration', self::roleOf($day, 'roman:sanctorale:simeon'));
    }

    public function testOnAFreeSaturdayOfTheNineteenthOfJanuaryBothSimplesAreCommemorated(): void
    {
        // 19 Jan 1952 is a Saturday: the Office of Our Lady takes the day and BOTH the coincident
        // Simples — Ss Marius & Companions and St Canute — are commemorated under it (the two-simples
        // dignity contest is moot when the votive Office is the day).
        $day = self::on(self::daYear(1952), '1952-01-19');

        self::assertSame(self::LADY, self::celebrationId($day));
        self::assertSame('commemoration', self::roleOf($day, 'roman:sanctorale:marius-et-socii'));
        self::assertSame('commemoration', self::roleOf($day, 'roman:sanctorale:canutus'));
    }

    // --- The Office yields (dropped, not commemorated) to a Semidouble-or-higher ---------

    public function testTheOfficeIsDroppedNotCommemoratedUnderASemidoubleFeast(): void
    {
        // 17 Jul 1954, a Saturday carrying the Semidouble of St Alexius: the feast takes the day
        // and the votive Office is DROPPED — displaced, never a commemoration (it is not a feast of
        // the saints of the day).
        $day = self::on(self::daYear(1954), '1954-07-17');

        self::assertSame('roman:sanctorale:alexius', self::celebrationId($day));
        self::assertSame('displaced', self::roleOf($day, self::LADY), 'A displaced lady office is dropped.');
        foreach ($day->commemoration() as $office) {
            self::assertNotSame(self::LADY, $office->id()->toString(), 'The lady office is never commemorated.');
        }
    }

    // --- The excluded seasons ------------------------------------------------------------

    public function testTheOfficeIsNotSaidOnAnAdventSaturday(): void
    {
        // Advent I 1954 is 28 Nov, so 11 Dec is a Saturday in Advent: the Office is not said (the
        // Advent feria is privileged). The last free Saturday BEFORE Advent (27 Nov) does keep it.
        self::assertNull(self::roleOf(self::on(self::daYear(1954), '1954-12-11'), self::LADY));
        self::assertSame(self::LADY, self::celebrationId(self::on(self::daYear(1954), '1954-11-27')));
        self::assertNull(self::roleOf(self::on(self::daYear(1954), '1954-12-04'), self::LADY));
    }

    public function testTheOfficeIsNotSaidInTheChristmasToEpiphanyBlock(): void
    {
        // 9 Jan 1954, a Saturday within the Christmas → Epiphany-octave block (24 Dec – 13 Jan).
        self::assertNull(self::roleOf(self::on(self::daYear(1954), '1954-01-09'), self::LADY));
    }

    public function testTheOfficeIsNotSaidOnALentenSaturday(): void
    {
        // 13 Mar 1954, an (Ember) Saturday of Lent: the privileged Lenten feria takes the day.
        self::assertNull(self::roleOf(self::on(self::daYear(1954), '1954-03-13'), self::LADY));
    }

    // --- Cum nostra (1955): retained, with MORE free Saturdays than 1954 -----------------

    public function testTheOfficeIsRetainedAndFreedByTheCollapsedOctavesUnder1955(): void
    {
        // 18 Aug 1951 is a Saturday within the Octave of the Assumption. Under 1954 the semidouble
        // day-within takes it (no lady); Cum nostra abolished that octave, so under 1955 the
        // Saturday is free and the Office of Our Lady is said — the reform's signature enlargement.
        self::assertSame(
            'roman:sanctorale:assumptio:infra-octavam:4',
            self::celebrationId(self::on(self::daYear(1951), '1951-08-18')),
            '1954 keeps the Assumption octave day-within.'
        );
        self::assertSame(
            self::LADY,
            self::celebrationId(self::on(self::cnYear(1951), '1951-08-18')),
            '1955 collapsed the octave, freeing the Saturday for the Office of Our Lady.'
        );
    }

    // --- 1962 never mints it (the base calendar is unmoved) ------------------------------

    public function testTheOfficeIsNeverMintedUnderThe1962Rubrics(): void
    {
        // The 1960 rubrics do not declare the archetype, so has() is false and no Saturday of the
        // year carries the votive Office — the 1962 golden fixture is unmoved by construction.
        $year = DayResolver::forEdition(RubricSystem::rubricae1960())->resolveYear(1954);
        $date = new DateTimeImmutable('1954-01-01', new DateTimeZone('UTC'));
        $oneWeek = new \DateInterval('P7D');
        while ((int) $date->format('w') !== 6) {
            $date = $date->add(new \DateInterval('P1D'));
        }
        for (; (int) $date->format('Y') === 1954; $date = $date->add($oneWeek)) {
            self::assertNull(
                self::roleOf($year->day($date), self::LADY),
                'The 1962 calendar never mints the Saturday Office (' . $date->format('Y-m-d') . ').'
            );
        }
    }
}
