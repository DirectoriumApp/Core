<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Sanctoral;

use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Sanctoral\SanctoralEntry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SanctoralEntryTest extends TestCase
{
    public function testExposesPlacementAndAttributes(): void
    {
        $entry = self::entry(8, 15);

        self::assertSame(8, $entry->month());
        self::assertSame(15, $entry->day());
        self::assertSame('I', $entry->rank()->label());
        self::assertSame('white', $entry->colour()->base()->value());
        self::assertSame('roman:sanctorale:assumptio', $entry->identity()->id()->toString());
    }

    public function testANonVigilEntryHasNoVigilLink(): void
    {
        $entry = self::entry(8, 15);

        self::assertFalse($entry->isVigil());
        self::assertNull($entry->vigilOfId());
    }

    /**
     * @dataProvider outOfRangeDates
     */
    public function testRejectsOutOfRangePlacement(int $month, int $day): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::entry($month, $day);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public function outOfRangeDates(): array
    {
        return [
            'month zero' => [0, 15],
            'month thirteen' => [13, 15],
            'day zero' => [8, 0],
            'day thirty-two' => [8, 32],
        ];
    }

    private static function entry(int $month, int $day): SanctoralEntry
    {
        return new SanctoralEntry(
            $month,
            $day,
            new Observance(
                ObservanceId::parse('roman:sanctorale:assumptio'),
                ObservanceKind::fromString(ObservanceKind::FEAST),
                ['maria'],
                ['la' => 'In Assumptione B.M.V.']
            ),
            RankClass::classI(),
            ElementColour::of(Colour::white())
        );
    }
}
