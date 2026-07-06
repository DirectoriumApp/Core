<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Precedence;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Attribute\Colour;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\LegacyRank;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Precedence\CommemorationSelector;
use Directorium\Core\Precedence\PrecedenceContext;
use Directorium\Core\Precedence\PrecedenceTable;
use Directorium\Core\Precedence\Rubrics1955Precedence;
use Directorium\Core\Sanctoral\SanctoralObservance;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalObservance;
use PHPUnit\Framework\TestCase;

/**
 * The 1955 interim (Cum nostra hac aetate) precedence engine (#70). These are edition-unit
 * tests: they construct observances of each grade and Sunday class and assert the tier
 * ordinal (from the roman-rubricae-1955 precedence table) and the reform outcomes Cum nostra
 * established — the abolition of the semidouble grade, the elevation of the Advent/Lent
 * Sundays, the 0/1/2 commemoration caps, and the privileged commemorations that survive over
 * them — independently of the sanctoral dataset. The tier ordinals are those in
 * editions/roman-rubricae-1955/precedence-tiers.ndjson.
 */
final class Rubrics1955PrecedenceTest extends TestCase
{
    /**
     * @dataProvider tierCases
     */
    public function testTierOrdinalOfEachGradeAndSundayClass(int $expectedOrdinal, RealizedObservance $office): void
    {
        self::assertSame($expectedOrdinal, self::tierOrdinal($office));
    }

    /**
     * @return array<string, array{int, RealizedObservance}>
     */
    public function tierCases(): array
    {
        return [
            'Christmas is the greatest' => [2, self::greatest()],
            'a first-class greater Sunday (Palm Sunday)' => [3, self::firstClassSunday()],
            'an Advent Sunday raised to the first class (Title II.3)' => [3, self::elevatedAdventSunday()],
            'Epiphany is a Double I class of the Lord' => [7, self::lordFeastFirstClass()],
            'a Double of the I class' => [7, self::dxi()],
            'a second-class greater Sunday (Septuagesima)' => [9, self::secondClassSunday()],
            'a Double of the II class' => [10, self::dxii()],
            'the Holy Name, a feast of the Lord below Double II' => [12, self::lordFeastLower()],
            'a lesser (per-annum) Sunday' => [13, self::lesserSunday()],
            'a greater (major) double' => [14, self::maius()],
            'an ordinary double' => [16, self::duplex()],
            'a common vigil' => [22, self::commonVigil()],
            'a former semidouble, now graded simplex' => [24, self::formerSemidouble()],
            'a former simple, reduced to a commemoration' => [29, self::formerSimple()],
        ];
    }

    public function testTheSemidoubleTierIsGoneFromTheNineteenFiftyFiveTableButNotNineteenFiftyFour(): void
    {
        // Title II.1 abolished the semidouble grade AND its tier. Asserted as a contrast so it is
        // not a tautology (tier() echoes the selector into its error): the 1954 table still HAS a
        // semidouble tier (line 17), the 1955 table has removed it.
        self::assertSame(
            17,
            (new PrecedenceTable(null, 'roman-divino-afflatu'))->tier('semidouble')->ordinal(),
            '1954 keeps the semidouble tier'
        );

        $this->expectException(\RuntimeException::class);
        (new PrecedenceTable(null, 'roman-rubricae-1955'))->tier('semidouble');
    }

    public function testAFormerSemidoubleMapsToTheSimpleTierAndAFormerSimpleToTheCommemorationFloor(): void
    {
        // The ENGINE's grade -> tier mapping of the two ALREADY-REDUCED grades (the reduction
        // itself — semiduplex -> simplex, simplex -> commemoratio — is the generator transform's
        // job, covered by deriveCumNostra1955's Node tests and the oracle fixture). A simple can
        // still be the day on a free feria; a commemoration takes the floor and never wins.
        self::assertSame(24, self::tierOrdinal(self::formerSemidouble()), 'a simplex grade -> simple tier');
        self::assertSame(29, self::tierOrdinal(self::formerSimple()), 'a commemoratio grade -> commemoration floor');
        self::assertTrue(self::outranks(self::duplex(), self::formerSimple()), 'a double outranks a commemoration');
        self::assertSame('commemorate', self::outcome(self::duplex(), self::formerSimple()));
    }

    public function testACommemorationOnlySaintIsRankedByItsGradeUnlessTrulyACommemoration(): void
    {
        // The COMMEMORATION_ONLY kind is a 1962 attribute carried on the shared identity. Under
        // 1955 a saint the 1960 reform later reduced may still be a real simplex office; only a
        // `commemoratio`-graded one takes the floor.
        $simplexOffice = self::sanctoral('probe-comm-simplex', 'commemoration-only', 4, LegacyRank::SIMPLEX);
        self::assertSame(24, self::tierOrdinal($simplexOffice), 'a simplex-graded commemoration takes the simple tier');

        $trueCommemoration = self::sanctoral('probe-comm', 'commemoration-only', 4, LegacyRank::COMMEMORATIO);
        self::assertSame(29, self::tierOrdinal($trueCommemoration), 'a commemoratio-graded office takes the floor');
    }

    public function testCommemorationCapsAreZeroOneTwoByDayClass(): void
    {
        // Title III.4: a first-class day admits no additional commemoration, a second-class day
        // one, any other day at most two.
        self::assertSame(0, self::rules()->commemorationLimit(self::dxi(), self::context()), 'first-class feast: 0');
        self::assertSame(1, self::rules()->commemorationLimit(self::dxii(), self::context()), 'second-class feast: 1');
        self::assertSame(2, self::rules()->commemorationLimit(self::maius(), self::context()), 'a greater double: 2');
        self::assertSame(2, self::rules()->commemorationLimit(self::simple(), self::context()), 'a simple: 2');

        // Easter admits none at all.
        $easter = self::temporal('roman:temporale:paschal:easter', 'sunday', 1, Season::EASTERTIDE);
        self::assertSame(0, self::rules()->commemorationLimit($easter, self::context()));
    }

    public function testAnElevatedSundayTakesTheFirstClassCommemorationCapDespiteItsSharedRank(): void
    {
        // The Advent Sundays Cum nostra raised to the first class (Title II.3) keep their shared
        // 1962 rank ATTRIBUTE of second class, but admit no additional commemoration — the
        // first-class cap is read from the elevated tier, not the ordinal (Title III.4a).
        self::assertSame(0, self::rules()->commemorationLimit(self::elevatedAdventSunday(), self::context()));

        // A genuinely lesser (per-annum) Sunday still admits one.
        self::assertSame(1, self::rules()->commemorationLimit(self::lesserSunday(), self::context()));
    }

    public function testPrivilegedCommemorationsAreKeptOverAZeroCap(): void
    {
        // Title III.2: on a first-class day (additional-commemoration count zero) a privileged
        // commemoration — here a Lenten feria — is still made, while an ordinary one is dropped.
        // This is the exemption the selector honours only for 1955.
        self::assertTrue(self::rules()->privilegedCommemorationsExemptFromLimit());

        $celebration = self::dxi();
        $lentenFeria = self::temporal('roman:temporale:paschal:lent-week-2:feria-4', 'feria', 3, Season::LENT);
        $ordinarySaint = self::sanctoral('probe-ordinary', 'feast', 4, LegacyRank::SIMPLEX);

        $selector = new CommemorationSelector(self::rules());
        $selected = $selector->select($celebration, [$ordinarySaint, $lentenFeria], self::context());

        $ids = array_map(static fn ($o) => $o->id()->toString(), $selected);
        self::assertContains('roman:temporale:paschal:lent-week-2:feria-4', $ids, 'the Lenten feria is kept');
        self::assertNotContains('roman:sanctorale:probe-ordinary', $ids, 'the ordinary commemoration is dropped');
    }

    public function testTheEditionDoesNotAnticipateSundayVigils(): void
    {
        // Title II.10: a common vigil on a Sunday is omitted, not anticipated to the preceding
        // Saturday (the rule 1962 also follows).
        self::assertFalse(self::rules()->anticipatesSundayVigils());
    }

    public function testOnlyFirstClassFeastsAreTransferred(): void
    {
        // Cum nostra restricts translation to feasts of the FIRST class: a first-class feast
        // impeded by a first-class Sunday is moved, but a SECOND-class feast is now commemorated
        // in place (verified vs DO Reduced-1955: Candlemas on Septuagesima, St Andrew on Advent I
        // — both commemorated, not transferred, where the pre-1955 rite moved them).
        $sunday = self::firstClassSunday();

        self::assertSame('transfer', self::outcome($sunday, self::dxi()));
        self::assertSame('commemorate', self::outcome($sunday, self::dxii()));
        self::assertSame('commemorate', self::outcome($sunday, self::maius()));
        self::assertSame('commemorate', self::outcome($sunday, self::duplex()));
        self::assertSame('commemorate', self::outcome($sunday, self::simple()));
    }

    public function testASanctoralMysteryOfTheLordTakesAPerAnnumSundaysPlace(): void
    {
        // Title II.7: a feast or mystery of the Lord takes a per-annum Sunday's place. The
        // Exaltation of the Cross, a greater double that would otherwise cede to the (higher)
        // lesser Sunday, is lifted above it and commemorates it (verified vs DO Reduced-1955,
        // 14 Sep 1958). An ordinary greater double still yields to the Sunday.
        $exaltation = self::sanctoral('exaltatio-crucis', 'feast', 3, LegacyRank::DUPLEX_MAIUS);
        $sunday = self::lesserSunday();

        self::assertTrue(self::outranks($exaltation, $sunday), 'the mystery of the Lord outranks the lesser Sunday');
        self::assertSame('commemorate', self::outcome($exaltation, $sunday));
        self::assertTrue(self::outranks($sunday, self::maius()), 'an ordinary greater double yields to the Sunday');
    }

    public function testAnOrdinaryFeriaIsOmittedButAGreaterFeriaIsCommemorated(): void
    {
        $feast = self::dxii();
        $greenFeria = self::temporal('roman:temporale:paschal:pentecost-time:feria-3', 'feria', 4, Season::PENTECOST);
        $lentenFeria = self::temporal('roman:temporale:paschal:lent-week-2:feria-3', 'feria', 3, Season::LENT);

        self::assertSame('omit', self::outcome($feast, $greenFeria));
        self::assertSame('commemorate', self::outcome($feast, $lentenFeria));
    }

    public function testTheNeverOmittedCommemorationsAreRecognisedAsPrivileged(): void
    {
        // Title III.2: any Sunday, a first-class feast (incl. the great feasts of the Lord), and
        // the ferias of Lent and Advent are privileged; an ordinary saint or a green feria is not.
        self::assertTrue(self::rules()->isPrivilegedCommemoration(self::lesserSunday()), 'any Sunday');
        self::assertTrue(self::rules()->isPrivilegedCommemoration(self::dxi()), 'a first-class feast');
        self::assertTrue(self::rules()->isPrivilegedCommemoration(self::lordFeastFirstClass()), 'a Lord feast I class');
        $lentenFeria = self::temporal('roman:temporale:paschal:lent-week-2:feria-3', 'feria', 3, Season::LENT);
        self::assertTrue(self::rules()->isPrivilegedCommemoration($lentenFeria), 'a Lenten feria');

        self::assertFalse(self::rules()->isPrivilegedCommemoration(self::simple()), 'an ordinary simple');
        $greenFeria = self::temporal('roman:temporale:paschal:pentecost-time:feria-3', 'feria', 4, Season::PENTECOST);
        self::assertFalse(self::rules()->isPrivilegedCommemoration($greenFeria), 'a green feria');
    }

    // --- construction helpers -------------------------------------------------------------

    private static function greatest(): RealizedObservance
    {
        return self::temporal('roman:temporale:christmas:nativity', 'feast', 1, Season::CHRISTMASTIDE);
    }

    private static function firstClassSunday(): RealizedObservance
    {
        return self::temporal('roman:temporale:paschal:palm-sunday', 'sunday', 1, Season::PASSIONTIDE);
    }

    private static function elevatedAdventSunday(): RealizedObservance
    {
        // Advent II carries the shared 1962 rank of the second class, yet Cum nostra (Title
        // II.3) makes it first class — the elevation lives in the first-class-Sunday membership.
        return self::temporal('roman:temporale:advent:sunday-2', 'sunday', 2, Season::ADVENT);
    }

    private static function secondClassSunday(): RealizedObservance
    {
        return self::temporal('roman:temporale:paschal:septuagesima', 'sunday', 2, Season::SEPTUAGESIMA);
    }

    private static function lesserSunday(): RealizedObservance
    {
        return self::temporal('roman:temporale:paschal:pentecost-time:sunday-11', 'sunday', 2, Season::PENTECOST);
    }

    private static function lordFeastFirstClass(): RealizedObservance
    {
        return self::temporal('roman:temporale:epiphany:domini', 'feast', 1, Season::EPIPHANY);
    }

    private static function lordFeastLower(): RealizedObservance
    {
        return self::temporal('roman:temporale:christmas:holy-name', 'feast', 2, Season::CHRISTMASTIDE);
    }

    private static function dxi(): RealizedObservance
    {
        return self::sanctoral('probe-dxi', 'feast', 1, LegacyRank::DUPLEX_I_CLASSIS);
    }

    private static function dxii(): RealizedObservance
    {
        return self::sanctoral('probe-dxii', 'feast', 2, LegacyRank::DUPLEX_II_CLASSIS);
    }

    private static function maius(): RealizedObservance
    {
        return self::sanctoral('probe-maius', 'feast', 3, LegacyRank::DUPLEX_MAIUS);
    }

    private static function duplex(): RealizedObservance
    {
        return self::sanctoral('probe-duplex', 'feast', 3, LegacyRank::DUPLEX);
    }

    /** A saint that was a semidouble under 1954 and is graded simplex by Cum nostra. */
    private static function formerSemidouble(): RealizedObservance
    {
        return self::sanctoral('probe-former-semi', 'feast', 4, LegacyRank::SIMPLEX);
    }

    /** A saint that was a simple under 1954 and is reduced to a commemoration by Cum nostra. */
    private static function formerSimple(): RealizedObservance
    {
        return self::sanctoral('probe-former-simple', 'feast', 4, LegacyRank::COMMEMORATIO);
    }

    private static function simple(): RealizedObservance
    {
        return self::sanctoral('probe-simplex', 'feast', 4, LegacyRank::SIMPLEX);
    }

    private static function commonVigil(): RealizedObservance
    {
        return self::sanctoral('probe-vigil', 'vigil', 4, LegacyRank::VIGILIA);
    }

    private static function rules(): Rubrics1955Precedence
    {
        return new Rubrics1955Precedence();
    }

    private static function context(): PrecedenceContext
    {
        return PrecedenceContext::of(new DateTimeImmutable('1957-07-01', new DateTimeZone('UTC')), false);
    }

    private static function tierOrdinal(RealizedObservance $office): int
    {
        return self::rules()->tierOf($office, self::context())->ordinal();
    }

    private static function outranks(RealizedObservance $a, RealizedObservance $b): bool
    {
        return self::rules()->tierOf($a, self::context())->isHigherThan(self::rules()->tierOf($b, self::context()));
    }

    private static function outcome(RealizedObservance $winner, RealizedObservance $loser): string
    {
        return self::rules()->occurrenceOutcome($winner, $loser, self::context())->value();
    }

    private static function temporal(string $id, string $kind, int $rank, string $season): RealizedObservance
    {
        return new TemporalObservance(
            ObservanceId::parse($id),
            ObservanceKind::fromString($kind),
            Season::fromString($season),
            RankClass::fromOrdinal($rank),
            ElementColour::of(Colour::white()),
            'Probatio'
        );
    }

    private static function sanctoral(
        string $slug,
        string $kind,
        int $rank,
        string $legacyRank,
        ?string $octaveOf = null
    ): RealizedObservance {
        return new SanctoralObservance(
            new Observance(
                ObservanceId::parse('roman:sanctorale:' . $slug),
                ObservanceKind::fromString($kind),
                [explode(':', $slug)[0]],
                ['la' => 'Probatio']
            ),
            RankClass::fromOrdinal($rank),
            ElementColour::of(Colour::white()),
            null,
            LegacyRank::fromString($legacyRank),
            $octaveOf !== null ? ObservanceId::parse($octaveOf) : null
        );
    }
}
