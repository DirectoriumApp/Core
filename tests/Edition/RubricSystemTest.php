<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Edition;

use Directorium\Core\Edition\RubricSystem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The rubric-system (edition) selector (#59/#60). The three traditional systems are built
 * and resolvable (#453); the Novus Ordo (Ordinary Form) is declared on the axis for v1.1
 * (#256) with `roman:novus-ordo-2002` as the build target and the 1969/1975 snapshots
 * reserved. The default resolves to 1962.
 */
final class RubricSystemTest extends TestCase
{
    public function testDefaultIsTheNineteenSixtyEdition(): void
    {
        self::assertSame(RubricSystem::RUBRICAE_1960, RubricSystem::default()->urn());
        self::assertTrue(RubricSystem::default()->equals(RubricSystem::rubricae1960()));
    }

    public function testFromStringAcceptsNullUrnAndAliases(): void
    {
        self::assertSame(RubricSystem::RUBRICAE_1960, RubricSystem::fromString(null)->urn());
        self::assertSame(RubricSystem::RUBRICAE_1960, RubricSystem::fromString('')->urn());
        self::assertSame(RubricSystem::RUBRICAE_1960, RubricSystem::fromString('roman:rubricae-1960')->urn());
        self::assertSame(RubricSystem::RUBRICAE_1960, RubricSystem::fromString('1962')->urn());
        self::assertSame(RubricSystem::RUBRICAE_1960, RubricSystem::fromString('1960')->urn());
        self::assertSame(RubricSystem::DIVINO_AFFLATU, RubricSystem::fromString('1954')->urn());
        self::assertSame(RubricSystem::DIVINO_AFFLATU, RubricSystem::fromString('divino-afflatu')->urn());
        self::assertSame(RubricSystem::RUBRICAE_1955, RubricSystem::fromString('1955')->urn());
    }

    public function testFromStringRejectsAnUnknownSelector(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // A system not declared on the axis at all (Tridentine is a future edition, not yet declared).
        RubricSystem::fromString('roman:tridentine-1570');
    }

    public function testBuiltSystemsAreBuiltAndReservedSnapshotsAreNot(): void
    {
        // The #453 burndown flipped 1954 and 1955 from declared to built; #256/#260 built the
        // Novus Ordo 2002.
        self::assertTrue(RubricSystem::rubricae1960()->isBuilt());
        self::assertTrue(RubricSystem::divinoAfflatu()->isBuilt());
        self::assertTrue(RubricSystem::rubricae1955()->isBuilt());
        self::assertTrue(RubricSystem::fromString(RubricSystem::NOVUS_ORDO_2002)->isBuilt());

        // The reserved 1969/1975 Novus-Ordo snapshots are declared on the axis but not yet
        // built; the isBuilt() flag keeps each refused at the public boundary until it is.
        self::assertFalse(RubricSystem::fromString(RubricSystem::NOVUS_ORDO_1969)->isBuilt());
        self::assertFalse(RubricSystem::fromString(RubricSystem::NOVUS_ORDO_1975)->isBuilt());
    }

    public function testNovusOrdoIsResolvableByUrnAndAliases(): void
    {
        self::assertSame(RubricSystem::NOVUS_ORDO_2002, RubricSystem::fromString('roman:novus-ordo-2002')->urn());
        self::assertSame(RubricSystem::NOVUS_ORDO_2002, RubricSystem::fromString('novus-ordo')->urn());
        self::assertSame(RubricSystem::NOVUS_ORDO_2002, RubricSystem::fromString('ordinary-form')->urn());
        self::assertSame(RubricSystem::NOVUS_ORDO_2002, RubricSystem::fromString('2002')->urn());
        self::assertSame(RubricSystem::NOVUS_ORDO_1969, RubricSystem::fromString('1969')->urn());
        self::assertSame(RubricSystem::NOVUS_ORDO_1975, RubricSystem::fromString('1975')->urn());
        self::assertSame('roman-novus-ordo-2002', RubricSystem::fromString('novus-ordo')->corpusDir());
        self::assertSame('cic-1983', RubricSystem::fromString('novus-ordo')->penitentialDiscipline());
    }

    public function testEachSystemCarriesItsCorpusDirectory(): void
    {
        self::assertSame('roman-rubricae-1960', RubricSystem::rubricae1960()->corpusDir());
        self::assertSame('roman-divino-afflatu', RubricSystem::divinoAfflatu()->corpusDir());
        self::assertSame('roman-rubricae-1955', RubricSystem::rubricae1955()->corpusDir());
    }

    public function testValidityWindows(): void
    {
        // 1962 is still in traditional use — open-ended.
        self::assertTrue(RubricSystem::rubricae1960()->governs(2026));
        self::assertTrue(RubricSystem::rubricae1960()->governs(1961));
        self::assertFalse(RubricSystem::rubricae1960()->governs(1955));

        // 1954 governed 1913–1955; 1955 governed 1956–1960.
        self::assertTrue(RubricSystem::divinoAfflatu()->governs(1954));
        self::assertFalse(RubricSystem::divinoAfflatu()->governs(1960));
        self::assertTrue(RubricSystem::rubricae1955()->governs(1958));
        self::assertFalse(RubricSystem::rubricae1955()->governs(1962));

        // The Novus Ordo 2002 snapshot governs 2002 onward; the reserved 1969 snapshot 1970–1974.
        self::assertTrue(RubricSystem::fromString(RubricSystem::NOVUS_ORDO_2002)->governs(2026));
        self::assertFalse(RubricSystem::fromString(RubricSystem::NOVUS_ORDO_2002)->governs(1990));
        self::assertTrue(RubricSystem::fromString(RubricSystem::NOVUS_ORDO_1969)->governs(1972));
    }

    public function testAllListsEveryDeclaredSystemIncludingUnbuilt(): void
    {
        $urns = array_map(static fn (RubricSystem $s): string => $s->urn(), RubricSystem::all());

        self::assertSame(
            [
                RubricSystem::DIVINO_AFFLATU,
                RubricSystem::RUBRICAE_1955,
                RubricSystem::RUBRICAE_1960,
                RubricSystem::NOVUS_ORDO_1969,
                RubricSystem::NOVUS_ORDO_1975,
                RubricSystem::NOVUS_ORDO_2002,
            ],
            $urns
        );
    }

    public function testCarriesAHumanLabel(): void
    {
        self::assertSame('Rubricae 1960 (1962)', RubricSystem::rubricae1960()->label());
        self::assertStringContainsString('1954', RubricSystem::divinoAfflatu()->label());
    }

    public function testCarriesItsSupersessionPointer(): void
    {
        // The edition-governance metadata (#366): each edition names the one that replaced it,
        // within its own line. The traditional line: 1954 → 1955 → 1962; the Novus-Ordo line:
        // 1969 → 1975 → 2002.
        self::assertSame(RubricSystem::RUBRICAE_1955, RubricSystem::divinoAfflatu()->supersededBy());
        self::assertSame(RubricSystem::RUBRICAE_1960, RubricSystem::rubricae1955()->supersededBy());
        self::assertSame(RubricSystem::NOVUS_ORDO_1975, RubricSystem::fromString('1969')->supersededBy());
        self::assertSame(RubricSystem::NOVUS_ORDO_2002, RubricSystem::fromString('1975')->supersededBy());

        // 1962 is still in authorised traditional use (the reform opened a parallel line, it did
        // not supersede it); the 2002 Novus Ordo is the current head, kept living by decrees (#366).
        self::assertNull(RubricSystem::rubricae1960()->supersededBy());
        self::assertNull(RubricSystem::fromString('novus-ordo')->supersededBy());
    }
}
