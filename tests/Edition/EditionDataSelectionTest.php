<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Edition;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Precedence\PrecedenceTable;
use Directorium\Core\Sanctoral\CorpusSanctoralData;
use PHPUnit\Framework\TestCase;

/**
 * The per-edition data seam (#62): the sanctoral loader and the precedence table now
 * read the edition directory the {@see RubricSystem} names, and the resolver is built
 * for an edition — with 1962 the only one wired, others reserved (#63/#68).
 */
final class EditionDataSelectionTest extends TestCase
{
    public function testSanctoralLoaderReadsTheNamedEditionDirectory(): void
    {
        $default = (new CorpusSanctoralData())->entries();
        $explicit = (new CorpusSanctoralData(null, 'roman-rubricae-1960'))->entries();

        self::assertNotSame([], $default);
        self::assertSameSize($default, $explicit);
        self::assertSame(
            CorpusSanctoralData::DEFAULT_EDITION_DIR,
            'roman-rubricae-1960'
        );
    }

    public function testPrecedenceTableReadsTheNamedEditionDirectory(): void
    {
        // The default and the explicit 1962 directory yield the same table facts.
        $table = new PrecedenceTable(null, 'roman-rubricae-1960');
        self::assertSame(PrecedenceTable::default()->commemorationLimit(1), $table->commemorationLimit(1));
        self::assertTrue($table->isMember('greatest', 'roman:temporale:paschal:easter'));
    }

    public function testForEditionBuildsAWorkingNineteenSixtyResolver(): void
    {
        $resolver = DayResolver::forEdition(RubricSystem::rubricae1960());
        $day = $resolver->resolveDay(new DateTimeImmutable('2025-12-25', new DateTimeZone('UTC')));

        self::assertSame('roman:temporale:christmas:nativity', $day->celebration()[0]->id()->toString());
        self::assertSame('roman:rubricae-1960', $resolver->provenance()->edition());
    }

    public function testForEditionBuildsTheInterimNineteenFiftyFiveResolver(): void
    {
        // The 1955 (Cum nostra) engine is now wired (#70), so forEdition() builds it and it
        // reads its own edition data — every registered system now has an engine, and the
        // resolver's remaining throw guards only a future, not-yet-implemented edition.
        $resolver = DayResolver::forEdition(RubricSystem::rubricae1955());
        $day = $resolver->resolveDay(new DateTimeImmutable('1958-12-25', new DateTimeZone('UTC')));

        self::assertSame('roman:temporale:christmas:nativity', $day->celebration()[0]->id()->toString());
        self::assertSame('roman:rubricae-1955', $resolver->provenance()->edition());
    }
}
