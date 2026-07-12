<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Decree;

use Directorium\Core\Attribute\Colour;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Decree\MovableDecree;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use PHPUnit\Framework\TestCase;

/**
 * A movable observance a decree adds (#366): the Blessed Virgin Mary, Mother of the
 * Church, placed on the Monday after Pentecost (Easter + 50) and realized as an
 * ordinary obligatory-memorial candidate.
 */
final class MovableDecreeTest extends TestCase
{
    private static function materEcclesiae(): MovableDecree
    {
        return new MovableDecree(
            new Observance(
                ObservanceId::parse('roman:sanctorale:maria-mater-ecclesiae'),
                ObservanceKind::fromString('feast'),
                ['beata-maria-virgo'],
                ['la' => 'Beatae Mariae Virginis, Ecclesiae Matris']
            ),
            RankClass::fromOrdinal(3),
            ElementColour::of(Colour::white()),
            50
        );
    }

    public function testPlacesItselfTheMondayAfterPentecost(): void
    {
        // Pentecost Monday = Easter + 50. Easter 2025 = 20 April, so the memorial falls 9 June;
        // Easter 2018 = 1 April, so 21 May; Easter 2026 = 5 April, so 25 May.
        self::assertSame('2025-06-09', self::materEcclesiae()->dateFor(2025)->format('Y-m-d'));
        self::assertSame('2018-05-21', self::materEcclesiae()->dateFor(2018)->format('Y-m-d'));
        self::assertSame('2026-05-25', self::materEcclesiae()->dateFor(2026)->format('Y-m-d'));
    }

    public function testRealizesAsAWhiteObligatoryMemorial(): void
    {
        $office = self::materEcclesiae()->realize();

        self::assertSame('roman:sanctorale:maria-mater-ecclesiae', $office->id()->toString());
        self::assertSame(3, $office->rank()->ordinal(), 'an obligatory memorial derives RankClass 3');
        self::assertSame('white', $office->colour()->base()->value());
        self::assertSame('Beatae Mariae Virginis, Ecclesiae Matris', $office->latinName());
    }

    public function testExposesItsEasterOffset(): void
    {
        self::assertSame(50, self::materEcclesiae()->easterOffset());
    }
}
