<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Precedence;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Attribute\Colour;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Precedence\NovusOrdoPrecedence;
use Directorium\Core\Precedence\PrecedenceContext;
use Directorium\Core\Precedence\PrecedenceTable;
use Directorium\Core\Temporal\Computus;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalObservance;
use PHPUnit\Framework\TestCase;

/**
 * The Novus-Ordo precedence engine (#257): the reformed Table of Liturgical Days
 * (Normae universales n. 59), read line by line, and the "no commemoration —
 * transfer or omit" occurrence rule. The tiers are read from the minimal Novus-Ordo
 * fixture table; the real cited table + full-year oracle validation are #108 / #260.
 */
final class NovusOrdoPrecedenceTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/novus-ordo-temporal';

    private function rules(): NovusOrdoPrecedence
    {
        return new NovusOrdoPrecedence(new PrecedenceTable(Corpus::at(self::FIXTURE), 'roman-novus-ordo-2002'));
    }

    private static function context(bool $triduum = false): PrecedenceContext
    {
        return PrecedenceContext::of(new DateTimeImmutable('2026-06-01', new DateTimeZone('UTC')), $triduum);
    }

    private static function temporal(string $id, string $kind, Season $season): TemporalObservance
    {
        return new TemporalObservance(
            ObservanceId::parse($id),
            ObservanceKind::fromString($kind),
            $season,
            RankClass::classII(),
            ElementColour::of(Colour::fromString('green')),
            'test'
        );
    }

    private static function sanctoral(string $id, string $kind, RankClass $rank): RealizedObservance
    {
        return new class ($id, $kind, $rank) implements RealizedObservance {
            private ObservanceId $id;
            private ObservanceKind $kind;
            private RankClass $rank;

            public function __construct(string $id, string $kind, RankClass $rank)
            {
                $this->id = ObservanceId::parse($id);
                $this->kind = ObservanceKind::fromString($kind);
                $this->rank = $rank;
            }

            public function id(): ObservanceId
            {
                return $this->id;
            }

            public function kind(): ObservanceKind
            {
                return $this->kind;
            }

            public function rank(): RankClass
            {
                return $this->rank;
            }

            public function colour(): ElementColour
            {
                return ElementColour::of(Colour::fromString('white'));
            }

            public function latinName(): string
            {
                return 'test';
            }
        };
    }

    /** @return array<string, array{RealizedObservance, int}> */
    public function tierCases(): array
    {
        return [
            'Ordinary-Time Sunday → line 6' =>
                [self::temporal('roman:temporale:ordinary-time:sunday-8', 'sunday', Season::ordinaryTime()), 6],
            'Ordinary-Time feria → line 13' =>
                [self::temporal('roman:temporale:ordinary-time:week-8:feria-2', 'feria', Season::ordinaryTime()), 13],
            'Sunday of Lent → line 2 (privileged)' =>
                [self::temporal('roman:temporale:paschal:lent:sunday-2', 'sunday', Season::lent()), 2],
            'weekday of Lent → line 9 (privileged)' =>
                [self::temporal('roman:temporale:paschal:lent:week-2:feria-2', 'feria', Season::lent()), 9],
            'named unmovable feast of the Lord (Pentecost) → line 2' =>
                [self::temporal('roman:temporale:paschal:pentecost', 'sunday', Season::eastertide()), 2],
            'Solemnity → line 3' =>
                [self::sanctoral('roman:sanctorale:assumptio', 'feast', RankClass::classI()), 3],
            'Feast of the Lord → line 5' =>
                [self::sanctoral('roman:sanctorale:transfiguratio', 'feast', RankClass::classII()), 5],
            'Feast of a saint → line 7' =>
                [self::sanctoral('roman:sanctorale:laurentius', 'feast', RankClass::classII()), 7],
            'obligatory memorial → line 10' =>
                [self::sanctoral('roman:sanctorale:athanasius', 'feast', RankClass::classIII()), 10],
            'optional memorial → line 12' =>
                [self::sanctoral('roman:sanctorale:pius-v', 'feast', RankClass::classIV()), 12],
            'All Souls → line 3' =>
                [self::sanctoral('roman:sanctorale:defuncti', 'office-of-the-dead', RankClass::classI()), 3],
        ];
    }

    /**
     * @dataProvider tierCases
     */
    public function testTierLine(RealizedObservance $observance, int $expectedLine): void
    {
        self::assertSame($expectedLine, $this->rules()->tierOf($observance, self::context())->line());
    }

    public function testTriduumFeriaTakesTheApexLine(): void
    {
        $feria = self::temporal('roman:temporale:paschal:holy-week:feria-6', 'feria', Season::eastertide());

        self::assertSame(1, $this->rules()->tierOf($feria, self::context(true))->line());
    }

    /** A Solemnity and a Feast of the Lord outrank a Sunday of Ordinary Time; a saint's Feast does not. */
    public function testFeastsOfTheLordAndSolemnitiesOutrankASunday(): void
    {
        $rules = $this->rules();
        $sunday = self::temporal('roman:temporale:ordinary-time:sunday-8', 'sunday', Season::ordinaryTime());
        $sundayTier = $rules->tierOf($sunday, self::context());

        $solemnity = self::sanctoral('roman:sanctorale:assumptio', 'feast', RankClass::classI());
        $lordFeast = self::sanctoral('roman:sanctorale:transfiguratio', 'feast', RankClass::classII());
        $saintFeast = self::sanctoral('roman:sanctorale:laurentius', 'feast', RankClass::classII());

        self::assertTrue($rules->tierOf($solemnity, self::context())->isHigherThan($sundayTier));
        self::assertTrue($rules->tierOf($lordFeast, self::context())->isHigherThan($sundayTier));
        self::assertTrue($sundayTier->isHigherThan($rules->tierOf($saintFeast, self::context())));
    }

    /** All Souls outranks a Sunday of Ordinary Time and does NOT yield to it (stays on 2 Nov). */
    public function testAllSoulsOutranksASundayAndDoesNotYield(): void
    {
        $rules = $this->rules();
        $allSouls = self::sanctoral('roman:sanctorale:defuncti', 'office-of-the-dead', RankClass::classI());
        $sunday = self::temporal('roman:temporale:ordinary-time:sunday-31', 'sunday', Season::ordinaryTime());

        $allSoulsTier = $rules->tierOf($allSouls, self::context());
        self::assertTrue($allSoulsTier->isHigherThan($rules->tierOf($sunday, self::context())));
        self::assertFalse($rules->officeOfTheDeadYieldsToSunday());
    }

    /** An impeded Solemnity is transferred; every other impeded office is omitted — never commemorated. */
    public function testImpededSolemnityTransfersOthersAreOmitted(): void
    {
        $rules = $this->rules();
        $winner = self::sanctoral('roman:sanctorale:winner', 'feria', RankClass::classI());

        $solemnity = self::sanctoral('roman:sanctorale:ioseph', 'feast', RankClass::classI());
        $feast = self::sanctoral('roman:sanctorale:marcus', 'feast', RankClass::classII());
        $memorial = self::sanctoral('roman:sanctorale:athanasius', 'feast', RankClass::classIII());

        self::assertTrue($rules->occurrenceOutcome($winner, $solemnity, self::context())->isTransfer());
        self::assertTrue($rules->occurrenceOutcome($winner, $feast, self::context())->isOmission());
        self::assertTrue($rules->occurrenceOutcome($winner, $memorial, self::context())->isOmission());

        foreach ([$solemnity, $feast, $memorial] as $loser) {
            self::assertFalse(
                $rules->occurrenceOutcome($winner, $loser, self::context())->isCommemoration(),
                'The reformed calendar never commemorates.'
            );
        }
    }

    public function testAdmitsNoCommemorations(): void
    {
        $rules = $this->rules();
        $office = self::sanctoral('roman:sanctorale:assumptio', 'feast', RankClass::classI());

        self::assertSame(0, $rules->commemorationLimit($office, self::context()));
        self::assertSame(0, $rules->commemorationClassLimit($office));
        self::assertFalse($rules->isPrivilegedCommemoration($office));
        self::assertFalse($rules->privilegedCommemorationsExemptFromLimit());
    }

    public function testReformedFlags(): void
    {
        $rules = $this->rules();

        self::assertFalse($rules->observesVespersConcurrence());
        self::assertFalse($rules->officeOfTheDeadYieldsToSunday());
        self::assertFalse($rules->anticipatesSundayVigils());
    }

    public function testConcurrenceGivesTheEveningToTheHigherOffice(): void
    {
        $rules = $this->rules();
        $solemnity = self::sanctoral('roman:sanctorale:assumptio', 'feast', RankClass::classI());
        $weekday = self::temporal('roman:temporale:ordinary-time:week-20:feria-3', 'feria', Season::ordinaryTime());

        self::assertTrue($rules->concurrenceOutcome($solemnity, $weekday, self::context())->equals(
            \Directorium\Core\Precedence\ConcurrenceOutcome::fullOfPreceding()
        ));
        self::assertTrue($rules->concurrenceOutcome($weekday, $solemnity, self::context())->equals(
            \Directorium\Core\Precedence\ConcurrenceOutcome::fullOfFollowing()
        ));
    }

    public function testAnnunciationHasAForcedTransferDate(): void
    {
        $rules = $this->rules();
        $context = PrecedenceContext::of(new DateTimeImmutable('2026-03-25', new DateTimeZone('UTC')), false);

        $annunciation = self::sanctoral('roman:sanctorale:annuntiatio', 'feast', RankClass::classI());
        $target = $rules->forcedTransferDate($annunciation, $context);
        self::assertNotNull($target);
        // Easter 2026 is 5 April; the Monday after the Octave of Easter is 13 April.
        self::assertSame('2026-04-13', $target->format('Y-m-d'));
        self::assertSame(Computus::gregorianEaster(2026)->format('Y-m-d'), '2026-04-05');

        $other = self::sanctoral('roman:sanctorale:laurentius', 'feast', RankClass::classI());
        self::assertNull($rules->forcedTransferDate($other, $context));
    }
}
