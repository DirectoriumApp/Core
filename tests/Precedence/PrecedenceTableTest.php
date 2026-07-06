<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Precedence;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Attribute\Colour;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Precedence\PrecedenceContext;
use Directorium\Core\Precedence\PrecedenceTable;
use Directorium\Core\Precedence\Rubrics1962Precedence;
use Directorium\Core\Temporal\Season;
use Directorium\Core\Temporal\TemporalObservance;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The precedence read seam (#43): the n.91 tier ordinals, the membership id-sets,
 * and the commemoration limits read from the corpus. The whole resolved calendar is
 * exercised end to end by the resolver tests (and locked byte-for-byte by the golden
 * fixture, which is itself the "matches the coded baseline" proof for the engine);
 * this pins the reader and proves that reordering the DATA reorders resolution with
 * no engine edit.
 */
final class PrecedenceTableTest extends TestCase
{
    /** @var list<string> Temp corpus roots to remove after each test. */
    private array $tempRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            $edDir = $root . '/editions/roman-rubricae-1960';
            @unlink($edDir . '/precedence-tiers.ndjson');
            @unlink($edDir . '/precedence-rules.ndjson');
            @rmdir($edDir);
            @rmdir($root . '/editions');
            @rmdir($root);
        }
        $this->tempRoots = [];
    }

    public function testTierOrdinalsAreReadFromTheTable(): void
    {
        $table = PrecedenceTable::default();

        self::assertSame(1, $table->tier('greatest')->ordinal());
        self::assertSame(2, $table->tier('triduum')->ordinal());
        // The n.91 gaps are preserved: second-class vigils are line 21, not 19.
        self::assertSame(21, $table->tier('second-vigil')->ordinal());
        self::assertSame(28, $table->tier('fourth')->ordinal());
    }

    public function testMembershipSetsAreReadFromTheTable(): void
    {
        $table = PrecedenceTable::default();

        self::assertTrue($table->isMember('greatest', 'roman:temporale:paschal:easter'));
        self::assertTrue($table->isMember('great-lady', 'roman:sanctorale:assumptio'));
        self::assertFalse($table->isMember('greatest', 'roman:sanctorale:laurentius'));
    }

    public function testCommemorationLimitsAreReadFromTheTable(): void
    {
        $table = PrecedenceTable::default();

        self::assertSame(1, $table->commemorationLimit(1));
        self::assertSame(1, $table->commemorationLimit(2));
        self::assertSame(2, $table->commemorationLimit(3));
        self::assertSame(2, $table->commemorationLimit(4));
    }

    /**
     * The commemoration limits are per-edition data, not a universal constant (#332): the
     * 1960 reform cut them to 1/1/2/2, the pre-1955 rite allowed three for every class, and
     * Cum nostra caps a first-class day at zero. The contract reports whichever the resolving
     * edition declares, so this pins the three tables the engine reads.
     *
     * @dataProvider editionCommemorationLimits
     *
     * @param array<int, int> $expected day-class => limit
     */
    public function testCommemorationLimitsArePerEditionData(string $editionDir, array $expected): void
    {
        $table = new PrecedenceTable(Corpus::default(), $editionDir);

        foreach ($expected as $class => $limit) {
            self::assertSame(
                $limit,
                $table->commemorationLimit($class),
                sprintf('%s commemoration limit for class %d', $editionDir, $class)
            );
        }
    }

    /**
     * @return array<string, array{string, array<int, int>}>
     */
    public function editionCommemorationLimits(): array
    {
        return [
            '1962 (Rubricae 1960)' => ['roman-rubricae-1960', [1 => 1, 2 => 1, 3 => 2, 4 => 2]],
            '1954 (Divino Afflatu)' => ['roman-divino-afflatu', [1 => 3, 2 => 3, 3 => 3, 4 => 3]],
            '1955 (Cum nostra)' => ['roman-rubricae-1955', [1 => 0, 2 => 1, 3 => 2, 4 => 2]],
        ];
    }

    public function testUnknownSelectorThrows(): void
    {
        $this->expectException(RuntimeException::class);
        PrecedenceTable::default()->tier('no-such-tier');
    }

    public function testUnknownMembershipSetThrows(): void
    {
        $this->expectException(RuntimeException::class);
        PrecedenceTable::default()->isMember('no-such-set', 'roman:temporale:paschal:easter');
    }

    public function testReorderingTheTierDataReordersResolutionWithNoEngineEdit(): void
    {
        // A first-class Sunday resolves to the "first-sunday" line — ordinal 6 in the
        // shipped 1962 table.
        $sunday = self::classOneSunday();
        self::assertSame(6, (new Rubrics1962Precedence())->tierOf($sunday, self::context())->ordinal());

        // Move that line to 99 in the DATA only, and the SAME engine resolves the same
        // Sunday to 99 — proof the ordering is data-driven, not coded.
        $reordered = new PrecedenceTable(Corpus::at($this->fixtureWithSundayOrdinal(99)));

        self::assertSame(99, $reordered->tier('first-sunday')->ordinal());
        self::assertSame(99, (new Rubrics1962Precedence($reordered))->tierOf($sunday, self::context())->ordinal());
    }

    /**
     * A temp corpus whose precedence-tiers give the "first-sunday" line the supplied
     * ordinal; the rules file is copied verbatim so membership and limits still load.
     */
    private function fixtureWithSundayOrdinal(int $ordinal): string
    {
        $realEdDir = dirname(__DIR__, 2) . '/data/corpus/editions/roman-rubricae-1960';
        $root = sys_get_temp_dir() . '/directorium-precedence-' . uniqid('', true);
        $edDir = $root . '/editions/roman-rubricae-1960';
        mkdir($edDir, 0777, true);
        $this->tempRoots[] = $root;

        copy($realEdDir . '/precedence-rules.ndjson', $edDir . '/precedence-rules.ndjson');

        $lines = [];
        foreach (explode("\n", (string) file_get_contents($realEdDir . '/precedence-tiers.ndjson')) as $line) {
            if ($line === '') {
                continue;
            }
            /** @var array<string, mixed> $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (($row['selector'] ?? null) === 'first-sunday') {
                $row['ordinal'] = $ordinal;
            }
            $lines[] = json_encode($row, JSON_THROW_ON_ERROR);
        }
        file_put_contents($edDir . '/precedence-tiers.ndjson', implode("\n", $lines) . "\n");

        return $root;
    }

    private static function classOneSunday(): RealizedObservance
    {
        return new TemporalObservance(
            ObservanceId::parse('roman:temporale:paschal:lent-1'),
            ObservanceKind::fromString(ObservanceKind::SUNDAY),
            Season::lent(),
            RankClass::fromOrdinal(1),
            ElementColour::of(Colour::violet()),
            'Dominica I in Quadragesima'
        );
    }

    private static function context(): PrecedenceContext
    {
        return PrecedenceContext::of(new DateTimeImmutable('2025-03-09', new DateTimeZone('UTC')), false);
    }
}
