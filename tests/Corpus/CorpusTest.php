<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Corpus;

use Introibo\Core\Corpus\Corpus;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CorpusTest extends TestCase
{
    public function testExposesTheManifestCorpusVersion(): void
    {
        $corpus = Corpus::default();

        $version = $corpus->corpusVersion();
        self::assertNotSame('', $version);
        self::assertSame($corpus->manifest()['corpusVersion'], $version);
    }

    public function testRecordsParseEveryNdjsonLineToAnObject(): void
    {
        $rows = Corpus::default()->identitySanctorale();

        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            self::assertArrayHasKey('id', $row);
            self::assertIsString($row['id']);
        }
    }

    public function testEveryPlacedObservanceHasAnIdentityAndAttributes(): void
    {
        $corpus = Corpus::default();

        // The shared identity is the cross-edition UNION of observances (Core v0.3.0): it may
        // list observances a given edition does not place — e.g. the 1954 sanctoral octaves,
        // which the 1960 edition has no placement for. So identity is a superset, not an exact
        // match; the invariant is per-edition referential integrity — every observance the
        // 1960 edition PLACES has both an identity and an attributes row, and the edition's
        // attributes and placement are one-to-one.
        $identityIds = $this->idsOf($corpus->identitySanctorale());
        $attributeIds = $this->idsOf($corpus->attributesSanctorale('roman-rubricae-1960'));
        $placement = $corpus->placementSanctorale('roman-rubricae-1960');

        self::assertGreaterThan(0, count($placement));
        self::assertCount(count($attributeIds), $placement, 'the 1960 edition attributes and placement are one-to-one');
        self::assertGreaterThanOrEqual(count($placement), count($identityIds), 'identity is a superset of any edition');

        foreach ($placement as $row) {
            self::assertArrayHasKey($row['id'], $identityIds, sprintf('placement "%s" has no identity', $row['id']));
            self::assertArrayHasKey($row['id'], $attributeIds, sprintf('placement "%s" has no attributes', $row['id']));
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, true>
     */
    private function idsOf(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[$row['id']] = true;
        }

        return $ids;
    }

    public function testAMissingCorpusFileThrowsClearly(): void
    {
        $this->expectException(RuntimeException::class);

        Corpus::at(__DIR__ . '/does-not-exist')->corpusVersion();
    }
}
