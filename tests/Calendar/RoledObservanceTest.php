<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Calendar;

use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Calendar\CelebrationRole;
use Introibo\Core\Calendar\RoledObservance;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

final class RoledObservanceTest extends TestCase
{
    /** A feast reduced to a commemoration keeps its full identity and attributes. */
    public function testAFeastCanBeMarkedAsACommemoration(): void
    {
        $roled = new RoledObservance(self::office('urbanus', 4, 'red'), CelebrationRole::commemoration());

        self::assertSame('commemoration', $roled->role()->value());
        self::assertSame('roman:sanctorale:urbanus', $roled->observance()->id()->toString());
        self::assertSame('IV', $roled->observance()->rank()->label());
        self::assertSame('red', $roled->observance()->colour()->base()->value());
    }

    /** A displaced office retains all the data the transfer queue (#34) needs. */
    public function testADisplacedOfficeRetainsFullData(): void
    {
        $office = self::office('georgius', 3, 'red', 'S. Georgii Martyris');
        $roled = new RoledObservance($office, CelebrationRole::displaced());

        self::assertSame('displaced', $roled->role()->value());
        self::assertSame($office, $roled->observance());
        self::assertSame('S. Georgii Martyris', $roled->observance()->latinName());
        self::assertSame('III', $roled->observance()->rank()->label());
    }

    public function testEqualsComparesRoleAndObservance(): void
    {
        $office = self::office('georgius', 3, 'red');
        $same = new RoledObservance($office, CelebrationRole::displaced());
        $sameValue = new RoledObservance(self::office('georgius', 3, 'red'), CelebrationRole::displaced());
        $otherRole = new RoledObservance($office, CelebrationRole::commemoration());

        self::assertTrue($same->equals($sameValue));
        self::assertFalse($same->equals($otherRole));
    }

    private static function office(
        string $slug,
        int $rankOrdinal,
        string $colour,
        string $latinName = 'S. Testis'
    ): SanctoralObservance {
        return new SanctoralObservance(
            new Observance(
                ObservanceId::parse('roman:sanctorale:' . $slug),
                ObservanceKind::fromString(ObservanceKind::FEAST),
                [$slug],
                ['la' => $latinName]
            ),
            RankClass::fromOrdinal($rankOrdinal),
            ElementColour::of(Colour::fromString($colour))
        );
    }
}
