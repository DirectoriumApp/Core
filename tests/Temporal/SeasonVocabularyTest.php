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

    /** The open union is exactly the traditional eight while only traditional editions are built. */
    public function testUnionIsTheTraditionalEight(): void
    {
        self::assertSame(self::TRADITIONAL, SeasonVocabulary::tokens());
    }

    /** Every traditional token is registered; a non-token (and the NO's future token) is not. */
    public function testIsRegistered(): void
    {
        foreach (self::TRADITIONAL as $token) {
            self::assertTrue(SeasonVocabulary::isRegistered($token), "$token must be registered.");
        }

        self::assertFalse(SeasonVocabulary::isRegistered('ordinary-time'));
        self::assertFalse(SeasonVocabulary::isRegistered('ordinary'));
        self::assertFalse(SeasonVocabulary::isRegistered(''));
    }

    /** The three built editions are registered, in historical order. */
    public function testEditionsAreTheThreeBuiltSystems(): void
    {
        self::assertSame(
            [
                RubricSystem::DIVINO_AFFLATU,
                RubricSystem::RUBRICAE_1955,
                RubricSystem::RUBRICAE_1960,
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

        SeasonVocabulary::subsetFor('roman:novus-ordo-2002');
    }

    public function testPermits(): void
    {
        self::assertTrue(SeasonVocabulary::permits(RubricSystem::RUBRICAE_1960, Season::LENT));
        self::assertFalse(SeasonVocabulary::permits(RubricSystem::RUBRICAE_1960, 'ordinary-time'));
    }
}
