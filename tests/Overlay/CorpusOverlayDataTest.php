<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Overlay;

use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Overlay\AddOperation;
use Directorium\Core\Overlay\CalendarOverlay;
use Directorium\Core\Overlay\CorpusOverlayData;
use Directorium\Core\Overlay\OverlaidSanctoralData;
use Directorium\Core\Overlay\RerankOperation;
use Directorium\Core\Overlay\SuppressOperation;
use Directorium\Core\Sanctoral\CorpusSanctoralData;
use Directorium\Core\Sanctoral\SanctoralEntry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The overlay loader (#76): it rebuilds a {@see CalendarOverlay} from the cited corpus
 * — the shipped SSPX overlay from the real tree, and all three operation kinds from a
 * temp fixture corpus. Layered over the base sanctoral it produces the particular
 * calendar with the engine untouched.
 */
final class CorpusOverlayDataTest extends TestCase
{
    /** @var list<string> */
    private array $tempRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            self::removeTree($root);
        }
        $this->tempRoots = [];
    }

    public function testShippedCorpusCarriesTheSspxOverlay(): void
    {
        $data = new CorpusOverlayData();

        self::assertContains('sspx', $data->slugs());
        self::assertTrue($data->has('sspx'));
        self::assertFalse($data->has('no-such-overlay'));
    }

    public function testLoadsTheSspxOverlayMetadataAndOperations(): void
    {
        $overlay = (new CorpusOverlayData())->overlay('sspx');

        self::assertSame('directorium:overlay:roman:sspx', $overlay->id());
        self::assertSame('Society of Saint Pius X', $overlay->name());
        self::assertCount(2, $overlay->operations());
        foreach ($overlay->operations() as $operation) {
            self::assertInstanceOf(RerankOperation::class, $operation);
        }
    }

    public function testSspxOverlayLayeredOverTheBaseElevatesItsFeasts(): void
    {
        $overlaid = new OverlaidSanctoralData(
            new CorpusSanctoralData(),
            (new CorpusOverlayData())->overlay('sspx')
        );
        $byId = self::indexById($overlaid->entries());

        $piusX = $byId['roman:sanctorale:pius-x'] ?? null;
        self::assertInstanceOf(SanctoralEntry::class, $piusX);
        self::assertSame(1, $piusX->rank()->ordinal(), 'St Pius X is first class under the SSPX calendar.');
        // The elevation re-cites the SSPX ordo; identity, date and colour are the base's.
        self::assertSame('sspx-ordo', $piusX->citations()->for('rank')->sourceKey());
        self::assertSame('mr-1920', $piusX->citations()->for('names.la')->sourceKey());
        self::assertSame(9, $piusX->month());
        self::assertSame(3, $piusX->day());
        self::assertSame('white', $piusX->colour()->base()->value());

        $sorrows = $byId['roman:sanctorale:septem-dolorum-bmv'] ?? null;
        self::assertInstanceOf(SanctoralEntry::class, $sorrows);
        self::assertSame(1, $sorrows->rank()->ordinal(), 'The Seven Sorrows (Sep 15) is first class under SSPX.');
        self::assertSame('sspx-ordo', $sorrows->citations()->for('rank')->sourceKey());

        // The overlay is a re-rank only: it adds and removes no feast.
        self::assertCount(count((new CorpusSanctoralData())->entries()), $overlaid->entries());
    }

    public function testVersionStampsTheOverlayOntoTheBase(): void
    {
        $overlaid = new OverlaidSanctoralData(
            new CorpusSanctoralData(),
            (new CorpusOverlayData())->overlay('sspx')
        );

        self::assertSame(
            Corpus::default()->corpusVersion() . '+directorium:overlay:roman:sspx',
            $overlaid->version()
        );
    }

    public function testUnknownOverlaySlugThrowsClearly(): void
    {
        $this->expectException(RuntimeException::class);
        (new CorpusOverlayData())->overlay('no-such-overlay');
    }

    public function testLoadsEveryOperationKindFromTheCorpus(): void
    {
        $data = new CorpusOverlayData(Corpus::at($this->fixtureCorpusWithAllOpKinds()));
        $overlay = $data->overlay('test');

        self::assertSame('directorium:overlay:roman:test', $overlay->id());
        self::assertCount(3, $overlay->operations());

        $byTarget = [];
        foreach ($overlay->operations() as $operation) {
            $byTarget[$operation->targetId()->toString()] = $operation;
        }

        self::assertInstanceOf(RerankOperation::class, $byTarget['roman:sanctorale:foo']);
        self::assertInstanceOf(AddOperation::class, $byTarget['roman:sanctorale:bar']);
        self::assertInstanceOf(SuppressOperation::class, $byTarget['roman:sanctorale:baz']);

        // The added feast is rebuilt whole from its self-contained corpus entry.
        $added = $byTarget['roman:sanctorale:bar']->applyTo([])['roman:sanctorale:bar'];
        self::assertInstanceOf(SanctoralEntry::class, $added);
        self::assertSame(6, $added->month());
        self::assertSame(12, $added->day());
        self::assertSame(3, $added->rank()->ordinal());
        self::assertSame('S. Bar Martyris', $added->identity()->latinName());
        self::assertSame('mr-1920', $added->citations()->for('names.la')->sourceKey());
        self::assertSame('sspx-ordo', $added->citations()->for('rank')->sourceKey());
    }

    /**
     * A temp corpus carrying one overlay `test` whose operations.ndjson exercises all
     * three kinds. Only the overlay files are needed — the loader touches nothing else.
     */
    private function fixtureCorpusWithAllOpKinds(): string
    {
        $root = sys_get_temp_dir() . '/directorium-overlay-' . uniqid('', true);
        $overlayDir = $root . '/overlays/test';
        mkdir($overlayDir, 0777, true);
        $this->tempRoots[] = $root;

        file_put_contents($root . '/MANIFEST.json', json_encode([
            'corpusVersion' => 'test-corpus',
            'overlays' => ['test'],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($overlayDir . '/overlay.json', json_encode([
            'id' => 'directorium:overlay:roman:test',
            'name' => 'Test overlay',
            'rite' => 'roman',
            'operations' => 3,
        ], JSON_THROW_ON_ERROR));

        $operations = [
            ['op' => 'rerank', 'target' => 'roman:sanctorale:foo', 'rank' => 1, 'cites' => ['rank' => 'sspx-ordo']],
            ['op' => 'add', 'entry' => [
                'id' => 'roman:sanctorale:bar',
                'kind' => 'feast',
                'titulars' => ['bar'],
                'names' => ['la' => 'S. Bar Martyris'],
                'rank' => 3,
                'colour' => ['base' => 'red'],
                'month' => 6,
                'day' => 12,
                'cites' => [
                    'names.la' => 'mr-1920',
                    'rank' => 'sspx-ordo',
                    'colour' => 'sspx-ordo',
                    'month' => 'sspx-ordo',
                    'day' => 'sspx-ordo',
                ],
            ]],
            ['op' => 'suppress', 'target' => 'roman:sanctorale:baz', 'cites' => ['suppressed' => 'sspx-ordo']],
        ];
        $lines = array_map(
            static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR),
            $operations
        );
        file_put_contents($overlayDir . '/operations.ndjson', implode("\n", $lines) . "\n");

        return $root;
    }

    /**
     * @param list<SanctoralEntry> $entries
     *
     * @return array<string, SanctoralEntry>
     */
    private static function indexById(array $entries): array
    {
        $byId = [];
        foreach ($entries as $entry) {
            $byId[$entry->identity()->id()->toString()] = $entry;
        }

        return $byId;
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::removeTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
