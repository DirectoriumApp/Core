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

    public function testTheThreeSanctoralShapesShareRecordCount(): void
    {
        $corpus = Corpus::default();

        $identity = count($corpus->identitySanctorale());
        self::assertGreaterThan(0, $identity);
        self::assertCount($identity, $corpus->attributesSanctorale('roman-rubricae-1960'));
        self::assertCount($identity, $corpus->placementSanctorale('roman-rubricae-1960'));
    }

    public function testAMissingCorpusFileThrowsClearly(): void
    {
        $this->expectException(RuntimeException::class);

        Corpus::at(__DIR__ . '/does-not-exist')->corpusVersion();
    }
}
