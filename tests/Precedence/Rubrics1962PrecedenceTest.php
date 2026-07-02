<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Precedence;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Calendar\RealizedObservance;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Precedence\PrecedenceContext;
use Introibo\Core\Precedence\PrecedenceTier;
use Introibo\Core\Precedence\Rubrics1962Precedence;
use Introibo\Core\Sanctoral\SanctoralObservance;
use Introibo\Core\Temporal\Season;
use Introibo\Core\Temporal\TemporalObservance;
use PHPUnit\Framework\TestCase;

/**
 * The 1962 tier map against the Table of Liturgical Days (Codex Rubricarum
 * n. 91). Ordinals are the n. 91 line numbers, so each case pins a category to
 * its exact line and the boundaries test proves the occurrence-critical order.
 */
final class Rubrics1962PrecedenceTest extends TestCase
{
    /**
     * @dataProvider tierCases
     */
    public function testTierOfMatchesTheN91Line(
        string $id,
        string $kind,
        int $rank,
        string $season,
        bool $triduum,
        int $expectedLine
    ): void {
        self::assertSame($expectedLine, self::lineOf(self::build($id, $kind, $rank, $season), $triduum));
    }

    /**
     * Each row is [id, kind, rank, season, triduum, n.91 line].
     *
     * @return array<string, array{string, string, int, string, bool, int}>
     */
    public function tierCases(): array
    {
        return [
            'Christmas (greatest)' =>
                ['roman:temporale:christmas:nativity', 'feast', 1, 'christmastide', false, 1],
            'Sacred Triduum' =>
                ['roman:temporale:paschal:good-friday', 'feria', 1, 'passiontide', true, 2],
            'Corpus Christi (great Lord)' =>
                ['roman:temporale:paschal:corpus-christi', 'feast', 1, 'pentecost', false, 3],
            'Immaculate Conception (great Lady)' =>
                ['roman:sanctorale:immaculata-conceptio', 'feast', 1, 'advent', false, 4],
            'Vigil of Christmas' =>
                ['roman:temporale:christmas:vigil', 'vigil', 1, 'advent', false, 5],
            'first-class Sunday' =>
                ['roman:temporale:advent:sunday-1', 'sunday', 1, 'advent', false, 6],
            'privileged first-class feria' =>
                ['roman:temporale:paschal:ash-wednesday', 'feria', 1, 'lent', false, 7],
            'All Souls' =>
                ['roman:sanctorale:omnes-fideles-defuncti', 'office-of-the-dead', 1, 'pentecost', false, 8],
            'Vigil of Pentecost' =>
                ['roman:temporale:paschal:pentecost-vigil', 'vigil', 1, 'eastertide', false, 9],
            'day within the Easter octave' =>
                ['roman:temporale:paschal:easter-octave', 'within-octave', 1, 'eastertide', false, 10],
            'first-class saint feast' =>
                ['roman:sanctorale:ioseph', 'feast', 1, 'lent', false, 11],
            'second-class feast of the Lord' =>
                ['roman:temporale:christmas:holy-name', 'feast', 2, 'christmastide', false, 14],
            'second-class Sunday' =>
                ['roman:temporale:epiphany:sunday-2', 'sunday', 2, 'epiphany', false, 15],
            'second-class saint feast' =>
                ['roman:sanctorale:stephanus', 'feast', 2, 'christmastide', false, 16],
            'day within the Christmas octave' =>
                ['roman:temporale:christmas:within-octave', 'within-octave', 2, 'christmastide', false, 17],
            'second-class feria' =>
                ['roman:temporale:advent:greater-feria', 'feria', 2, 'advent', false, 18],
            'second-class vigil' =>
                ['roman:sanctorale:ioannes-baptista:vigilia', 'vigil', 2, 'pentecost', false, 21],
            'third-class Lenten feria' =>
                ['roman:temporale:paschal:lent-feria', 'feria', 3, 'lent', false, 22],
            'third-class feast' =>
                ['roman:sanctorale:thomas-aquinas', 'feast', 3, 'lent', false, 24],
            'third-class Advent feria' =>
                ['roman:temporale:advent:feria', 'feria', 3, 'advent', false, 25],
            'third-class vigil' =>
                ['roman:sanctorale:laurentius:vigilia', 'vigil', 3, 'pentecost', false, 26],
            'Saturday Office of Our Lady' =>
                ['roman:sanctorale:sancta-maria-sabbato', 'lady-on-saturday', 4, 'pentecost', false, 27],
            'fourth-class feria' =>
                ['roman:temporale:epiphany:feria', 'feria', 4, 'epiphany', false, 28],
            'fourth-class commemoration' =>
                ['roman:sanctorale:quatuor-coronati', 'commemoration-only', 4, 'pentecost', false, 28],
        ];
    }

    /** The occurrence-critical boundaries n. 91 fixes — why a raw class compare is not enough. */
    public function testKeyOccurrenceBoundaries(): void
    {
        $immaculata = self::build('roman:sanctorale:immaculata-conceptio', 'feast', 1, 'advent');
        $firstSunday = self::build('roman:temporale:advent:sunday-1', 'sunday', 1, 'advent');
        $ashWednesday = self::build('roman:temporale:paschal:ash', 'feria', 1, 'lent');
        $stJoseph = self::build('roman:sanctorale:ioseph', 'feast', 1, 'lent');
        $secondSunday = self::build('roman:temporale:advent:sunday-2', 'sunday', 2, 'advent');
        $lordFeastII = self::build('roman:temporale:christmas:holy-name', 'feast', 2, 'christmastide');
        $saintFeastII = self::build('roman:sanctorale:stephanus', 'feast', 2, 'christmastide');
        $christmasOctave = self::build('roman:temporale:christmas:oct', 'within-octave', 2, 'christmastide');
        $lentFeria = self::build('roman:temporale:paschal:lent', 'feria', 3, 'lent');
        $thirdFeast = self::build('roman:sanctorale:thomas-aquinas', 'feast', 3, 'lent');
        $adventFeria = self::build('roman:temporale:advent:fer', 'feria', 3, 'advent');

        // A privileged first-class feria outranks a first-class feast (why the
        // Annunciation is transferred out of Holy Week). n. 91 line 7 < 11.
        self::assertTrue(self::tier($ashWednesday)->isHigherThan(self::tier($stJoseph)));
        // A first-class Sunday outranks a first-class saint feast (St Joseph → transfer). 6 < 11.
        self::assertTrue(self::tier($firstSunday)->isHigherThan(self::tier($stJoseph)));
        // The Immaculate Conception outranks a second-class Advent Sunday (feast wins). 4 < 15.
        self::assertTrue(self::tier($immaculata)->isHigherThan(self::tier($secondSunday)));
        // Second class: feast of the Lord > Sunday > saint feast > Christmas-octave day. 14<15<16<17.
        self::assertTrue(self::tier($lordFeastII)->isHigherThan(self::tier($secondSunday)));
        self::assertTrue(self::tier($secondSunday)->isHigherThan(self::tier($saintFeastII)));
        self::assertTrue(self::tier($saintFeastII)->isHigherThan(self::tier($christmasOctave)));
        // A Lenten feria outranks a third-class feast; a third-class feast outranks an Advent feria. 22<24<25.
        self::assertTrue(self::tier($lentFeria)->isHigherThan(self::tier($thirdFeast)));
        self::assertTrue(self::tier($thirdFeast)->isHigherThan(self::tier($adventFeria)));
    }

    public function testFirstClassFeastImpededByAHigherDayIsTransferred(): void
    {
        // St Joseph (I, 19 Mar) on a first-class Sunday of Lent → transferred (n. 95).
        $lentenSunday = self::build('roman:temporale:paschal:lent-sunday-3', 'sunday', 1, 'lent');
        $stJoseph = self::build('roman:sanctorale:ioseph', 'feast', 1, 'lent');

        self::assertSame('transfer', self::outcome($lentenSunday, $stJoseph));
    }

    public function testImpededSundayIsCommemoratedUnderAFirstClassFeast(): void
    {
        // Immaculate Conception (I) over a II-class Advent Sunday → Sunday commemorated (n. 108a).
        $immaculata = self::build('roman:sanctorale:immaculata-conceptio', 'feast', 1, 'advent');
        $adventSunday = self::build('roman:temporale:advent:sunday-2', 'sunday', 2, 'advent');

        self::assertSame('commemorate', self::outcome($immaculata, $adventSunday));
    }

    public function testOrdinaryFeastOnAFirstClassDayIsOmitted(): void
    {
        // A first-class day admits only a privileged commemoration (n. 111a).
        $firstClassSunday = self::build('roman:temporale:advent:sunday-1', 'sunday', 1, 'advent');
        $thirdClassSaint = self::build('roman:sanctorale:thomas-aquinas', 'feast', 3, 'advent');

        self::assertSame('omit', self::outcome($firstClassSunday, $thirdClassSaint));
    }

    public function testFirstClassFeastInAPrivilegedOctaveIsTransferred(): void
    {
        // Transfer (n. 95) is decided before the octave's no-commemoration rule.
        $easterOctave = self::build('roman:temporale:paschal:easter-octave', 'within-octave', 1, 'eastertide');
        $annunciation = self::build('roman:sanctorale:annuntiatio', 'feast', 1, 'eastertide');

        self::assertSame('transfer', self::outcome($easterOctave, $annunciation));
    }

    public function testLowerFeastInAPrivilegedOctaveIsOmitted(): void
    {
        // The Easter octave days admit no commemoration (n. 66); a II/III/IV feast is dropped.
        $easterOctave = self::build('roman:temporale:paschal:easter-octave', 'within-octave', 1, 'eastertide');
        $thirdClassSaint = self::build('roman:sanctorale:georgius', 'feast', 3, 'eastertide');

        self::assertSame('omit', self::outcome($easterOctave, $thirdClassSaint));
    }

    public function testTheTriduumAdmitsNoCommemoration(): void
    {
        $goodFriday = self::build('roman:temporale:paschal:good-friday', 'feria', 1, 'passiontide');
        $saint = self::build('roman:sanctorale:test-saint', 'feast', 3, 'passiontide');

        self::assertSame('omit', self::outcome($goodFriday, $saint, true));
    }

    public function testSecondClassSaintFeastIsCommemoratedUnderASecondClassSunday(): void
    {
        // II-class Sunday (tier 15) beats a II-class saint feast (tier 16); the feast is commemorated.
        $secondSunday = self::build('roman:temporale:epiphany:sunday-3', 'sunday', 2, 'epiphany');
        $saintFeastII = self::build('roman:sanctorale:cathedra-petri', 'feast', 2, 'epiphany');

        self::assertSame('commemorate', self::outcome($secondSunday, $saintFeastII));
    }

    public function testThirdClassFeastIsCommemoratedUnderASecondClassSunday(): void
    {
        $secondSunday = self::build('roman:temporale:epiphany:sunday-3', 'sunday', 2, 'epiphany');
        $thirdClassSaint = self::build('roman:sanctorale:thomas-aquinas', 'feast', 3, 'epiphany');

        self::assertSame('commemorate', self::outcome($secondSunday, $thirdClassSaint));
    }

    public function testThirdClassFeastIsCommemoratedUnderALentenFeria(): void
    {
        // A Lenten feria (III, tier 22) outranks a III-class feast (tier 24), which is commemorated.
        $lentenFeria = self::build('roman:temporale:paschal:lent-feria', 'feria', 3, 'lent');
        $thirdClassSaint = self::build('roman:sanctorale:thomas-aquinas', 'feast', 3, 'lent');

        self::assertSame('commemorate', self::outcome($lentenFeria, $thirdClassSaint));
    }

    public function testFourthClassCommemorationIsCommemoratedUnderAFeria(): void
    {
        $feria = self::build('roman:temporale:epiphany:feria', 'feria', 4, 'epiphany');
        $commemoration = self::build('roman:sanctorale:quatuor-coronati', 'commemoration-only', 4, 'epiphany');

        self::assertSame('commemorate', self::outcome($feria, $commemoration));
    }

    public function testSecondClassFeastIsOmittedNotTransferredOnAFirstClassDay(): void
    {
        // Only first-class feasts transfer (n. 95): a II-class saint feast impeded by a
        // first-class day is omitted, since a first-class day admits only privileged commemorations.
        $firstClassSunday = self::build('roman:temporale:advent:sunday-1', 'sunday', 1, 'advent');
        $saintFeastII = self::build('roman:sanctorale:cathedra-petri', 'feast', 2, 'advent');

        self::assertSame('omit', self::outcome($firstClassSunday, $saintFeastII));
    }

    public function testAFeastOfTheLordDoesNotCommemorateTheSundayItDisplaces(): void
    {
        // n. 15 / n. 112(b): the Holy Name (II feast of the Lord) over a II-class
        // Sunday → the Sunday is omitted, not commemorated.
        $holyName = self::build('roman:temporale:christmas:holy-name', 'feast', 2, 'christmastide');
        $secondSunday = self::build('roman:temporale:epiphany:sunday-2', 'sunday', 2, 'epiphany');

        self::assertSame('omit', self::outcome($holyName, $secondSunday));
    }

    public function testAMarianFeastStillCommemoratesTheSundayItDisplaces(): void
    {
        // Contrast: the Immaculate Conception is a feast of Our Lady, not of the
        // Lord, so the exclusion does not apply — the Sunday is commemorated.
        $immaculata = self::build('roman:sanctorale:immaculata-conceptio', 'feast', 1, 'advent');
        $secondSunday = self::build('roman:temporale:advent:sunday-2', 'sunday', 2, 'advent');

        self::assertSame('commemorate', self::outcome($immaculata, $secondSunday));
    }

    public function testASundayDoesNotCommemorateAnImpededFeastOfTheLord(): void
    {
        // The exclusion is symmetric: a first-class Sunday impeding a second-class
        // feast of the Lord omits it rather than commemorating it.
        $firstClassSunday = self::build('roman:temporale:advent:sunday-1', 'sunday', 1, 'advent');
        $lordFeastII = self::build('roman:temporale:christmas:holy-name', 'feast', 2, 'christmastide');

        self::assertSame('omit', self::outcome($firstClassSunday, $lordFeastII));
    }

    public function testAnnunciationHasAForcedTransferToTheMondayAfterLowSunday(): void
    {
        // Easter 2025 is 20 April; Low Sunday 27 April; the Monday after is 28 April.
        $annunciation = self::build('roman:sanctorale:annuntiatio', 'feast', 1, 'lent');
        $target = (new Rubrics1962Precedence())->forcedTransferDate(
            $annunciation,
            PrecedenceContext::of(new DateTimeImmutable('2025-03-25', new DateTimeZone('UTC')), false)
        );

        self::assertNotNull($target);
        self::assertSame('2025-04-28', $target->format('Y-m-d'));
    }

    public function testMostFeastsHaveNoForcedTransferDate(): void
    {
        $stJoseph = self::build('roman:sanctorale:ioseph', 'feast', 1, 'lent');
        $target = (new Rubrics1962Precedence())->forcedTransferDate(
            $stJoseph,
            PrecedenceContext::of(new DateTimeImmutable('2025-03-19', new DateTimeZone('UTC')), false)
        );

        self::assertNull($target);
    }

    private static function outcome(
        RealizedObservance $winner,
        RealizedObservance $loser,
        bool $triduum = false
    ): string {
        return (new Rubrics1962Precedence())
            ->occurrenceOutcome($winner, $loser, self::context($triduum))
            ->value();
    }

    private static function tier(RealizedObservance $office): PrecedenceTier
    {
        return (new Rubrics1962Precedence())->tierOf($office, self::context(false));
    }

    private static function lineOf(RealizedObservance $office, bool $triduum): int
    {
        return (new Rubrics1962Precedence())->tierOf($office, self::context($triduum))->ordinal();
    }

    private static function context(bool $triduum): PrecedenceContext
    {
        return PrecedenceContext::of(new DateTimeImmutable('2025-01-01', new DateTimeZone('UTC')), $triduum);
    }

    private static function build(string $id, string $kind, int $rank, string $season): RealizedObservance
    {
        $observanceId = ObservanceId::parse($id);
        $observanceKind = ObservanceKind::fromString($kind);
        $rankClass = RankClass::fromOrdinal($rank);
        $white = ElementColour::of(Colour::white());

        if (strpos($id, ':temporale:') !== false) {
            return new TemporalObservance(
                $observanceId,
                $observanceKind,
                Season::fromString($season),
                $rankClass,
                $white,
                'Testis'
            );
        }

        $subject = substr($id, (int) strrpos($id, ':') + 1);

        return new SanctoralObservance(
            new Observance($observanceId, $observanceKind, [$subject], ['la' => 'Testis']),
            $rankClass,
            $white
        );
    }
}
