<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Sanctoral;

use Directorium\Core\Attribute\Colour;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

final class SanctoralObservanceTest extends TestCase
{
    public function testDelegatesIdentityAndCarriesAttributes(): void
    {
        $office = self::office('roman:sanctorale:laurentius', 'S. Laurentii Martyris', 3, 'red');

        self::assertInstanceOf(RealizedObservance::class, $office);
        self::assertSame('roman:sanctorale:laurentius', $office->id()->toString());
        self::assertSame('feast', $office->kind()->value());
        self::assertSame('S. Laurentii Martyris', $office->latinName());
        self::assertSame('III', $office->rank()->label());
        self::assertSame('red', $office->colour()->base()->value());
    }

    public function testIdentityAccessorReturnsTheShell(): void
    {
        $office = self::office('roman:sanctorale:laurentius', 'S. Laurentii Martyris', 3, 'red');

        self::assertInstanceOf(Observance::class, $office->identity());
        self::assertSame(['laurentius'], $office->identity()->titulars());
    }

    public function testEqualsComparesIdentityAndAttributes(): void
    {
        $a = self::office('roman:sanctorale:laurentius', 'S. Laurentii Martyris', 3, 'red');
        $b = self::office('roman:sanctorale:laurentius', 'S. Laurentii Martyris', 3, 'red');
        $differentRank = self::office('roman:sanctorale:laurentius', 'S. Laurentii Martyris', 2, 'red');
        $differentId = self::office('roman:sanctorale:stephanus', 'S. Laurentii Martyris', 3, 'red');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($differentRank));
        self::assertFalse($a->equals($differentId));
    }

    private static function office(string $slug, string $latin, int $rankOrdinal, string $colour): SanctoralObservance
    {
        $subject = substr($slug, strrpos($slug, ':') + 1);

        return new SanctoralObservance(
            new Observance(
                ObservanceId::parse($slug),
                ObservanceKind::fromString(ObservanceKind::FEAST),
                [$subject],
                ['la' => $latin]
            ),
            RankClass::fromOrdinal($rankOrdinal),
            ElementColour::of(Colour::fromString($colour))
        );
    }
}
