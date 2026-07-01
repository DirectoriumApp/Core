<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Temporal;

use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Temporal\Season;
use Introibo\Core\Temporal\TemporalObservance;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TemporalObservanceTest extends TestCase
{
    public function testExposesEveryComponent(): void
    {
        $obs = self::sunday();

        self::assertSame('roman:temporale:advent:sunday-1', $obs->id()->toString());
        self::assertTrue($obs->kind()->equals(ObservanceKind::fromString(ObservanceKind::SUNDAY)));
        self::assertTrue($obs->season()->equals(Season::advent()));
        self::assertSame('I', $obs->rank()->label());
        self::assertSame(Colour::VIOLET, $obs->colour()->base()->value());
        self::assertFalse($obs->colour()->roseAllowed());
        self::assertSame('Dominica I Adventus', $obs->latinName());
    }

    public function testEmptyLatinNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TemporalObservance(
            ObservanceId::parse('roman:temporale:advent:sunday-1'),
            ObservanceKind::fromString(ObservanceKind::SUNDAY),
            Season::advent(),
            RankClass::classI(),
            ElementColour::of(Colour::violet()),
            ''
        );
    }

    public function testEqualsIsTrueForIdenticalComponents(): void
    {
        self::assertTrue(self::sunday()->equals(self::sunday()));
    }

    /**
     * @dataProvider distinctObservances
     */
    public function testEqualsIsFalseWhenAnyComponentDiffers(TemporalObservance $other): void
    {
        self::assertFalse(self::sunday()->equals($other));
    }

    /**
     * @return array<string, array{TemporalObservance}>
     */
    public function distinctObservances(): array
    {
        return [
            'different id' => [new TemporalObservance(
                ObservanceId::parse('roman:temporale:advent:sunday-2'),
                ObservanceKind::fromString(ObservanceKind::SUNDAY),
                Season::advent(),
                RankClass::classI(),
                ElementColour::of(Colour::violet()),
                'Dominica I Adventus'
            )],
            'different kind' => [new TemporalObservance(
                ObservanceId::parse('roman:temporale:advent:sunday-1'),
                ObservanceKind::fromString(ObservanceKind::FERIA),
                Season::advent(),
                RankClass::classI(),
                ElementColour::of(Colour::violet()),
                'Dominica I Adventus'
            )],
            'different season' => [new TemporalObservance(
                ObservanceId::parse('roman:temporale:advent:sunday-1'),
                ObservanceKind::fromString(ObservanceKind::SUNDAY),
                Season::christmastide(),
                RankClass::classI(),
                ElementColour::of(Colour::violet()),
                'Dominica I Adventus'
            )],
            'different rank' => [new TemporalObservance(
                ObservanceId::parse('roman:temporale:advent:sunday-1'),
                ObservanceKind::fromString(ObservanceKind::SUNDAY),
                Season::advent(),
                RankClass::classII(),
                ElementColour::of(Colour::violet()),
                'Dominica I Adventus'
            )],
            'different colour' => [new TemporalObservance(
                ObservanceId::parse('roman:temporale:advent:sunday-1'),
                ObservanceKind::fromString(ObservanceKind::SUNDAY),
                Season::advent(),
                RankClass::classI(),
                ElementColour::violetWithRose(),
                'Dominica I Adventus'
            )],
            'different name' => [new TemporalObservance(
                ObservanceId::parse('roman:temporale:advent:sunday-1'),
                ObservanceKind::fromString(ObservanceKind::SUNDAY),
                Season::advent(),
                RankClass::classI(),
                ElementColour::of(Colour::violet()),
                'Dominica II Adventus'
            )],
        ];
    }

    private static function sunday(): TemporalObservance
    {
        return new TemporalObservance(
            ObservanceId::parse('roman:temporale:advent:sunday-1'),
            ObservanceKind::fromString(ObservanceKind::SUNDAY),
            Season::advent(),
            RankClass::classI(),
            ElementColour::of(Colour::violet()),
            'Dominica I Adventus'
        );
    }
}
