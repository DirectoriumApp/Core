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
use Introibo\Core\Precedence\CommemorationSelector;
use Introibo\Core\Precedence\PrecedenceContext;
use Introibo\Core\Precedence\Rubrics1962Precedence;
use Introibo\Core\Sanctoral\SanctoralObservance;
use Introibo\Core\Temporal\Season;
use Introibo\Core\Temporal\TemporalObservance;
use PHPUnit\Framework\TestCase;

final class CommemorationSelectorTest extends TestCase
{
    public function testTrimsToTheDayLimitKeepingPrivilegedFirst(): void
    {
        // A first-class day admits one commemoration; the Sunday (privileged) is kept.
        $celebration = self::sanctoral('roman:sanctorale:ioseph', 'feast', 1);
        $sunday = self::temporal('roman:temporale:advent:sunday-2', 'sunday', 2, 'advent');
        $ordinaryFeast = self::sanctoral('roman:sanctorale:cathedra-petri', 'feast', 2);

        $kept = self::select($celebration, [$ordinaryFeast, $sunday]);

        self::assertCount(1, $kept);
        self::assertSame('roman:temporale:advent:sunday-2', $kept[0]->id()->toString());
    }

    public function testThirdClassDayAdmitsTwoCommemorations(): void
    {
        $celebration = self::temporal('roman:temporale:paschal:lent-feria', 'feria', 3, 'lent');
        $a = self::sanctoral('roman:sanctorale:aaa', 'feast', 3);
        $b = self::sanctoral('roman:sanctorale:bbb', 'feast', 3);
        $c = self::sanctoral('roman:sanctorale:ccc', 'feast', 3);

        self::assertCount(2, self::select($celebration, [$a, $b, $c]));
    }

    public function testAZeroCommemorationDayAdmitsNone(): void
    {
        $celebration = self::temporal('roman:temporale:paschal:easter-octave', 'within-octave', 1, 'eastertide');
        $candidate = self::sanctoral('roman:sanctorale:georgius', 'feast', 3);

        self::assertSame([], self::select($celebration, [$candidate]));
    }

    /**
     * @param list<RealizedObservance> $candidates
     *
     * @return list<RealizedObservance>
     */
    private static function select(RealizedObservance $celebration, array $candidates): array
    {
        $rules = new Rubrics1962Precedence();
        $context = PrecedenceContext::of(new DateTimeImmutable('2025-01-01', new DateTimeZone('UTC')), false);

        return (new CommemorationSelector($rules))->select($celebration, $candidates, $context);
    }

    private static function temporal(string $id, string $kind, int $rank, string $season): TemporalObservance
    {
        return new TemporalObservance(
            ObservanceId::parse($id),
            ObservanceKind::fromString($kind),
            Season::fromString($season),
            RankClass::fromOrdinal($rank),
            ElementColour::of(Colour::white()),
            'Testis'
        );
    }

    private static function sanctoral(string $id, string $kind, int $rank): SanctoralObservance
    {
        $subject = substr($id, (int) strrpos($id, ':') + 1);

        return new SanctoralObservance(
            new Observance(
                ObservanceId::parse($id),
                ObservanceKind::fromString($kind),
                [$subject],
                ['la' => 'Testis']
            ),
            RankClass::fromOrdinal($rank),
            ElementColour::of(Colour::white())
        );
    }
}
