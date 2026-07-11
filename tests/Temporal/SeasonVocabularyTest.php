<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal;

use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\SeasonVocabulary;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The open, edition-scoped season vocabulary (#364): the registry {@see Season}
 * validates against, and the per-edition subsets that make `season` edition-scoped.
 */
final class SeasonVocabularyTest extends TestCase
{
    /** The eight traditional tempora, in calendar order — the union today. */
    private const TRADITIONAL = [
        Season::ADVENT,
        Season::CHRISTMASTIDE,
        Season::EPIPHANY,
        Season::SEPTUAGESIMA,
        Season::LENT,
        Season::PASSIONTIDE,
        Season::EASTERTIDE,
        Season::PENTECOST,
    ];

    /**
     * The open union is the traditional eight plus the Novus Ordo's one new token,
     * `ordinary-time` (appended when the NO subset is folded in) — the shared tokens
     * are deduped, so the union grows by exactly one member.
     */
    public function testUnionIsTheTraditionalEightPlusOrdinaryTime(): void
    {
        $expected = array_merge(self::TRADITIONAL, [Season::ORDINARY_TIME]);

        self::assertSame($expected, SeasonVocabulary::tokens());
    }

    /** Every traditional token and the NO's `ordinary-time` are registered; a non-token is not. */
    public function testIsRegistered(): void
    {
        foreach (self::TRADITIONAL as $token) {
            self::assertTrue(SeasonVocabulary::isRegistered($token), "$token must be registered.");
        }

        self::assertTrue(SeasonVocabulary::isRegistered(Season::ORDINARY_TIME));
        self::assertFalse(SeasonVocabulary::isRegistered('ordinary'));
        self::assertFalse(SeasonVocabulary::isRegistered(''));
    }

    /** The registered editions, in historical order — the three traditional plus the NO 2002 snapshot. */
    public function testEditionsAreTheRegisteredSystems(): void
    {
        self::assertSame(
            [
                RubricSystem::DIVINO_AFFLATU,
                RubricSystem::RUBRICAE_1955,
                RubricSystem::RUBRICAE_1960,
                RubricSystem::NOVUS_ORDO_2002,
            ],
            SeasonVocabulary::editions()
        );
    }

    /**
     * Each built edition admits the full traditional subset today, so the
     * reclassification is byte-identical for every edition that exists.
     *
     * @dataProvider builtEditions
     */
    public function testSubsetForEachBuiltEditionIsTheTraditionalEight(string $edition): void
    {
        self::assertSame(self::TRADITIONAL, SeasonVocabulary::subsetFor($edition));
    }

    /** @return iterable<string, array{string}> */
    public function builtEditions(): iterable
    {
        yield 'divino-afflatu' => [RubricSystem::DIVINO_AFFLATU];
        yield 'rubricae-1955' => [RubricSystem::RUBRICAE_1955];
        yield 'rubricae-1960' => [RubricSystem::RUBRICAE_1960];
    }

    /** The union is the union of every edition's subset — the diff-alignment invariant. */
    public function testUnionEqualsTheUnionOfEditionSubsets(): void
    {
        $union = [];
        foreach (SeasonVocabulary::editions() as $edition) {
            foreach (SeasonVocabulary::subsetFor($edition) as $token) {
                $union[$token] = true;
            }
        }

        self::assertSame(SeasonVocabulary::tokens(), array_keys($union));
    }

    public function testSubsetForUnknownEditionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // A reserved-but-unbuilt snapshot has no season subset registered until it is built.
        SeasonVocabulary::subsetFor('roman:novus-ordo-1969');
    }

    public function testPermits(): void
    {
        self::assertTrue(SeasonVocabulary::permits(RubricSystem::RUBRICAE_1960, Season::LENT));
        self::assertFalse(SeasonVocabulary::permits(RubricSystem::RUBRICAE_1960, 'ordinary-time'));
    }

    /**
     * The Novus-Ordo subset drops Septuagesima/Passiontide/Epiphany/Pentecost and adds
     * `ordinary-time`; the shared tokens advent/lent/eastertide keep their bare names.
     */
    public function testNovusOrdoSubset(): void
    {
        self::assertSame(
            [Season::ADVENT, Season::CHRISTMASTIDE, Season::ORDINARY_TIME, Season::LENT, Season::EASTERTIDE],
            SeasonVocabulary::subsetFor(RubricSystem::NOVUS_ORDO_2002)
        );

        self::assertTrue(SeasonVocabulary::permits(RubricSystem::NOVUS_ORDO_2002, Season::ORDINARY_TIME));
        self::assertTrue(SeasonVocabulary::permits(RubricSystem::NOVUS_ORDO_2002, Season::ADVENT));
        self::assertFalse(SeasonVocabulary::permits(RubricSystem::NOVUS_ORDO_2002, Season::SEPTUAGESIMA));
        self::assertFalse(SeasonVocabulary::permits(RubricSystem::NOVUS_ORDO_2002, Season::PASSIONTIDE));

        // `ordinary-time` is now in the open union (registered by at least one edition).
        self::assertTrue(SeasonVocabulary::isRegistered(Season::ORDINARY_TIME));
    }
}
