<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Observance;

use Directorium\Core\Observance\IdentityAliases;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ObservanceTest extends TestCase
{
    public function testHoldsIdentity(): void
    {
        $observance = new Observance(
            ObservanceId::parse('roman:sanctorale:laurentius'),
            ObservanceKind::fromString(ObservanceKind::FEAST),
            ['laurentius'],
            ['la' => 'S. Laurentii Martyris', 'en' => 'St Lawrence, Martyr']
        );

        self::assertSame('roman:sanctorale:laurentius', $observance->id()->toString());
        self::assertSame('feast', $observance->kind()->value());
        self::assertSame(['laurentius'], $observance->titulars());
        self::assertSame('S. Laurentii Martyris', $observance->latinName());
        self::assertSame('St Lawrence, Martyr', $observance->name('en'));
        self::assertNull($observance->name('fr'));
        self::assertTrue($observance->aliases()->isEmpty());
    }

    public function testCompositeTitulars(): void
    {
        $observance = new Observance(
            ObservanceId::parse('roman:sanctorale:petrus-paulus'),
            ObservanceKind::fromString(ObservanceKind::FEAST),
            ['petrus', 'paulus'],
            ['la' => 'Ss. Petri et Pauli Apostolorum']
        );

        self::assertSame(['petrus', 'paulus'], $observance->titulars());
    }

    public function testRequiresAtLeastOneTitular(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Observance(
            ObservanceId::parse('roman:sanctorale:laurentius'),
            ObservanceKind::fromString(ObservanceKind::FEAST),
            [],
            ['la' => 'S. Laurentii']
        );
    }

    public function testRequiresLatinName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Observance(
            ObservanceId::parse('roman:sanctorale:laurentius'),
            ObservanceKind::fromString(ObservanceKind::FEAST),
            ['laurentius'],
            ['en' => 'St Lawrence']
        );
    }

    public function testSecondaryFacetRecordsDoubleIdentity(): void
    {
        $lowSunday = ObservanceId::parse('roman:temporale:paschal:low-sunday');
        $octaveDay = ObservanceId::parse('roman:temporale:paschal:octave-day-of-easter');

        $observance = new Observance(
            $lowSunday,
            ObservanceKind::fromString(ObservanceKind::SUNDAY),
            ['low-sunday'],
            ['la' => 'Dominica in Albis'],
            new IdentityAliases([$octaveDay])
        );

        $facets = $observance->aliases()->secondaryFacet();

        self::assertFalse($observance->aliases()->isEmpty());
        self::assertSame(
            ['roman:temporale:paschal:octave-day-of-easter'],
            array_map(static fn (ObservanceId $facet): string => $facet->toString(), $facets)
        );
    }
}
