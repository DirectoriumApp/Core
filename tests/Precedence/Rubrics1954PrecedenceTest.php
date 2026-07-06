<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Precedence;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\LegacyRank;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Calendar\RealizedObservance;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Precedence\PrecedenceContext;
use Introibo\Core\Precedence\Rubrics1954Precedence;
use Introibo\Core\Sanctoral\SanctoralObservance;
use Introibo\Core\Temporal\Season;
use Introibo\Core\Temporal\TemporalObservance;
use PHPUnit\Framework\TestCase;

/**
 * The pre-1955 (Divino Afflatu) precedence engine (#67). These are edition-unit tests:
 * they construct observances of each grade and Sunday class and assert the tier ordinal
 * (from the divino-afflatu precedence table) and the occurrence outcome, proving the
 * accuracy-critical rules the research established (verified against the DA Rubricae
 * Generales and the St. Lawrence Press pre-1955 Ordo) independently of the sanctoral
 * dataset. The tier ordinals are those in editions/roman-divino-afflatu/precedence-tiers.ndjson.
 */
final class Rubrics1954PrecedenceTest extends TestCase
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
            'Epiphany is a Double I class of the Lord' => [7, self::lordFeastFirstClass()],
            'a Double of the I class' => [7, self::dxi()],
            'a second-class greater Sunday (Sexagesima)' => [9, self::secondClassSunday()],
            'a Double of the II class' => [10, self::dxii()],
            'the Holy Name, a feast of the Lord below Double II' => [12, self::lordFeastLower()],
            'a lesser (per-annum) Sunday' => [13, self::lesserSunday()],
            'a greater (major) double' => [14, self::maius()],
            'an ordinary double' => [16, self::duplex()],
            'a semidouble' => [17, self::semidouble()],
            'a common vigil' => [22, self::commonVigil()],
            'a simple' => [24, self::simple()],
        ];
    }

    public function testTheLesserSundayOutranksEveryOrdinaryDoubleAndBelow(): void
    {
        // The Divino Afflatu elevation: a green Sunday keeps the day and the ordinary double
        // (or semidouble, simple) is only commemorated (Tit. IV §2; ordo-1954).
        $sunday = self::lesserSunday();
        $lower = [
            'greater double' => self::maius(),
            'ordinary double' => self::duplex(),
            'semidouble' => self::semidouble(),
        ];
        foreach ($lower as $label => $feast) {
            self::assertTrue(self::outranks($sunday, $feast), 'the lesser Sunday must outrank a ' . $label);
            self::assertSame('commemorate', self::outcome($sunday, $feast), $label . ' is commemorated on the Sunday');
        }
    }

    public function testOnlyDoublesOfTheFirstAndSecondClassAreTransferred(): void
    {
        // Tit. IV §3-4: only a Double of the I or II class is translated when impeded; every
        // lower grade is commemorated (or omitted) in place, never transferred.
        $sunday = self::firstClassSunday();

        self::assertSame('transfer', self::outcome($sunday, self::dxi()));
        self::assertSame('transfer', self::outcome($sunday, self::dxii()));
        self::assertSame('commemorate', self::outcome($sunday, self::maius()));
        self::assertSame('commemorate', self::outcome($sunday, self::duplex()));
        self::assertSame('commemorate', self::outcome($sunday, self::semidouble()));
        self::assertSame('commemorate', self::outcome($sunday, self::simple()));
    }

    public function testASecondClassSundayCedesOnlyToADoubleFirstClassAndTransfersTheLoser(): void
    {
        // A II-class greater Sunday yields only to a Double I class; a Double II class does
        // NOT displace it — the Sunday is celebrated and the Double II transferred (the
        // Candlemas-on-Sexagesima outcome, ordo-1954, T1).
        $sexagesima = self::secondClassSunday();

        self::assertTrue(self::outranks($sexagesima, self::dxii()));
        self::assertSame('transfer', self::outcome($sexagesima, self::dxii()));
    }

    public function testAnImpededSundayIsCommemoratedNeverOmitted(): void
    {
        // Unlike 1962 (where a feast of the Lord and a Sunday do not commemorate each other),
        // the pre-1955 rite commemorates the impeded lesser Sunday under the Lord's feast.
        self::assertSame('commemorate', self::outcome(self::lordFeastLower(), self::lesserSunday()));
    }

    public function testACommonOctaveIsOmittedUnderADoubleOfTheFirstOrSecondClass(): void
    {
        // A day within a common octave (semidouble grade) is suppressed under a Double of the
        // I or II class, but commemorated under anything lower (ordo-1954, T7).
        $within = self::commonOctaveWithin();

        self::assertSame('omit', self::outcome(self::dxi(), $within));
        self::assertSame('omit', self::outcome(self::dxii(), $within));
        self::assertSame('commemorate', self::outcome(self::maius(), $within));
    }

    public function testASimpleOctaveIsOmittedOnlyUnderADoubleOfTheFirstClass(): void
    {
        // A simple-octave day is suppressed only by a Double of the I class; under a Double
        // of the II class it is still commemorated.
        $simpleOctave = self::simpleOctaveDay();

        self::assertSame('omit', self::outcome(self::dxi(), $simpleOctave));
        self::assertSame('commemorate', self::outcome(self::dxii(), $simpleOctave));
    }

    public function testAnOrdinaryFeriaIsOmittedButAGreaterFeriaIsCommemorated(): void
    {
        $feast = self::dxii();
        $greenFeria = self::temporal('roman:temporale:paschal:pentecost-time:feria-3', 'feria', 4, Season::PENTECOST);
        $lentenFeria = self::temporal('roman:temporale:paschal:lent-week-2:feria-3', 'feria', 3, Season::LENT);

        self::assertSame('omit', self::outcome($feast, $greenFeria));
        self::assertSame('commemorate', self::outcome($feast, $lentenFeria));
    }

    public function testThePreNineteenFiftyFiveRiteAdmitsMoreThanOneCommemoration(): void
    {
        // No flat 1962-style cap (I=1): a first-class day admits the odd-orations bound of 3.
        self::assertSame(3, self::rules()->commemorationLimit(self::dxi(), self::context()));

        // Easter admits none.
        $easter = self::temporal('roman:temporale:paschal:easter', 'sunday', 1, Season::EASTERTIDE);
        self::assertSame(0, self::rules()->commemorationLimit($easter, self::context()));
    }

    public function testTheEditionAnticipatesSundayVigils(): void
    {
        self::assertTrue(self::rules()->anticipatesSundayVigils());
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

    private static function secondClassSunday(): RealizedObservance
    {
        return self::temporal('roman:temporale:paschal:sexagesima', 'sunday', 2, Season::SEPTUAGESIMA);
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

    private static function semidouble(): RealizedObservance
    {
        return self::sanctoral('probe-semi', 'feast', 3, LegacyRank::SEMIDUPLEX);
    }

    private static function simple(): RealizedObservance
    {
        return self::sanctoral('probe-simplex', 'feast', 4, LegacyRank::SIMPLEX);
    }

    private static function commonVigil(): RealizedObservance
    {
        return self::sanctoral('probe-vigil', 'vigil', 4, LegacyRank::VIGILIA);
    }

    private static function commonOctaveWithin(): RealizedObservance
    {
        return self::sanctoral(
            'assumptio:infra-octavam:4',
            'within-octave',
            3,
            LegacyRank::SEMIDUPLEX,
            'roman:sanctorale:assumptio'
        );
    }

    private static function simpleOctaveDay(): RealizedObservance
    {
        return self::sanctoral(
            'laurentius:in-octava',
            'octave-day',
            4,
            LegacyRank::SIMPLEX,
            'roman:sanctorale:laurentius'
        );
    }

    private static function rules(): Rubrics1954Precedence
    {
        return new Rubrics1954Precedence();
    }

    private static function context(): PrecedenceContext
    {
        return PrecedenceContext::of(new DateTimeImmutable('1954-07-01', new DateTimeZone('UTC')), false);
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
