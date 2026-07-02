<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Calendar;

use DateTimeImmutable;
use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Calendar\CelebrationRole;
use Introibo\Core\Calendar\LiturgicalDay;
use Introibo\Core\Calendar\RoledObservance;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

final class LiturgicalDayTest extends TestCase
{
    public function testPlaceholderDayIsEmptyForItsDate(): void
    {
        $date = new DateTimeImmutable('2026-06-30');
        $day = LiturgicalDay::placeholder($date);

        self::assertSame($date, $day->date());
        self::assertTrue($day->isEmpty());
        self::assertSame([], $day->celebration());
        self::assertSame([], $day->commemoration());
        self::assertSame([], $day->displaced());
        self::assertSame([], $day->tempora());
    }

    public function testExposesEachRole(): void
    {
        $date = new DateTimeImmutable('2026-08-10');
        $celebrated = self::observance('roman:sanctorale:laurentius', ObservanceKind::FEAST, 'laurentius');
        $commemorated = self::observance('roman:votive:beata-maria-virgo', ObservanceKind::LADY_ON_SATURDAY, 'bmv');
        $displaced = self::observance('roman:sanctorale:hippolytus', ObservanceKind::COMMEMORATION_ONLY, 'hippolytus');
        $temporal = self::observance('roman:temporale:paschal:feria', ObservanceKind::FERIA, 'paschal');

        $day = new LiturgicalDay(
            $date,
            [new RoledObservance($celebrated, CelebrationRole::celebration())],
            [new RoledObservance($commemorated, CelebrationRole::commemoration())],
            [new RoledObservance($displaced, CelebrationRole::displaced())],
            [new RoledObservance($temporal, CelebrationRole::tempora())]
        );

        self::assertFalse($day->isEmpty());
        self::assertTrue($day->celebration()[0]->id()->equals($celebrated->id()));
        self::assertTrue($day->commemoration()[0]->id()->equals($commemorated->id()));
        self::assertTrue($day->displaced()[0]->id()->equals($displaced->id()));
        self::assertTrue($day->tempora()[0]->id()->equals($temporal->id()));
    }

    public function testReturnedCollectionCannotMutateTheAggregate(): void
    {
        $date = new DateTimeImmutable('2026-08-10');
        $celebrated = self::observance('roman:sanctorale:laurentius', ObservanceKind::FEAST, 'laurentius');
        $day = new LiturgicalDay(
            $date,
            [new RoledObservance($celebrated, CelebrationRole::celebration())],
            [],
            [],
            []
        );

        $celebration = $day->celebration();
        $celebration[] = self::observance('roman:sanctorale:hippolytus', ObservanceKind::FEAST, 'hippolytus');

        // The aggregate handed back a copy; appending to it leaves the day unchanged.
        self::assertCount(1, $day->celebration());
    }

    private static function observance(string $id, string $kind, string $titular): SanctoralObservance
    {
        return new SanctoralObservance(
            new Observance(
                ObservanceId::parse($id),
                ObservanceKind::fromString($kind),
                [$titular],
                ['la' => ucfirst($titular)]
            ),
            RankClass::classIII(),
            ElementColour::of(Colour::white())
        );
    }
}
