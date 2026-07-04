<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Edition;

use Introibo\Core\Edition\RubricSystem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The rubric-system (edition) selector (#59/#60). Three systems sit on the edition
 * axis; only 1962 is built today, and the default resolves to it.
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
        RubricSystem::fromString('roman:novus-ordo-2002');
    }

    public function testOnlyNineteenSixtyIsBuilt(): void
    {
        self::assertTrue(RubricSystem::rubricae1960()->isBuilt());
        self::assertFalse(RubricSystem::divinoAfflatu()->isBuilt());
        self::assertFalse(RubricSystem::rubricae1955()->isBuilt());
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
    }

    public function testAllListsEveryDeclaredSystemIncludingUnbuilt(): void
    {
        $urns = array_map(static fn (RubricSystem $s): string => $s->urn(), RubricSystem::all());

        self::assertSame(
            [RubricSystem::DIVINO_AFFLATU, RubricSystem::RUBRICAE_1955, RubricSystem::RUBRICAE_1960],
            $urns
        );
    }

    public function testCarriesAHumanLabel(): void
    {
        self::assertSame('Rubricae 1960 (1962)', RubricSystem::rubricae1960()->label());
        self::assertStringContainsString('1954', RubricSystem::divinoAfflatu()->label());
    }
}
