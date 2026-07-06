<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

/**
 * Validation harness (#45), historical editions — the engine's 1954 (Divino Afflatu) and
 * 1955 (Cum nostra) resolution vs the Divinum Officium oracle (Epic #68, issue #71).
 *
 * The engine is resolved through {@see DayResolver::forEdition()} and its celebrated office —
 * feast id and legacy grade — is asserted against a pinned cross-section of the day-by-day
 * comparison against DO's `Divino Afflatu - 1954` and `Reduced - 1955` engines. The fixture is
 * static (see tests/Validation/fixtures/divinum-officium/README.md), so the oracle proof rides
 * in CI without a Perl or Divinum Officium checkout; the live full-year sweep is the
 * maintainer's {@see DivinumOfficiumOracle}.
 *
 * The rows deliberately span the reform's signature outcomes — the semidouble reduction (the
 * SAME saint, St Alexius, graded `semiduplex` on the 1954 fixture and `simplex` on the 1955
 * one), a mystery of the Lord taking a per-annum Sunday's place (the Exaltation of the Cross),
 * and the retained first- and second-class feasts. The differences the grade-level engine does
 * not resolve are catalogued in known-differences.ndjson and asserted well-formed below.
 */
final class HistoricalEditionOracleTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures/divinum-officium';

    /** The recognised known-difference categories — a new one means a mislabelled row. */
    private const CATEGORIES = [
        'tabella-apostle-vs-martyr',
        '1955-instituted-feast',
        'grade-source-disagreement',
        'deferred-temporal-feast',
        'deferred-office-of-the-dead',
    ];

    /**
     * @dataProvider agreementRows
     */
    public function testEngineMatchesTheDivinumOfficiumOracle(
        string $editionUrn,
        string $date,
        string $expectedId,
        string $expectedGrade,
        string $doOffice
    ): void {
        $resolver = DayResolver::forEdition(RubricSystem::fromString($editionUrn));
        $day = $resolver->resolveDay(new DateTimeImmutable($date, new DateTimeZone('UTC')));

        $celebration = $day->celebration();
        self::assertNotSame([], $celebration, "No office resolved for $date ($editionUrn).");
        $office = $celebration[0];

        self::assertSame(
            $expectedId,
            $office->id()->toString(),
            "Celebrated office changed on $date; DO ($editionUrn) has: $doOffice"
        );
        self::assertInstanceOf(SanctoralObservance::class, $office, "Expected a sanctoral office on $date.");
        $legacyRank = $office->legacyRank();
        self::assertNotNull($legacyRank, "Celebrated office carries no legacy grade on $date.");
        self::assertSame(
            $expectedGrade,
            $legacyRank->value(),
            "Legacy grade changed on $date; DO ($editionUrn) has: $doOffice"
        );
    }

    /**
     * @return array<string, array{string, string, string, string, string}>
     */
    public function agreementRows(): array
    {
        $files = [
            RubricSystem::DIVINO_AFFLATU => 'divino-afflatu-1954.ndjson',
            RubricSystem::RUBRICAE_1955 => 'reduced-1955.ndjson',
        ];

        $cases = [];
        foreach ($files as $urn => $file) {
            foreach ($this->readNdjson($file) as $row) {
                /** @var array{date: string, id: string, grade: string, do: string} $row */
                $cases[$urn . ' ' . $row['date'] . ' ' . $row['id']] = [
                    $urn,
                    $row['date'],
                    $row['id'],
                    $row['grade'],
                    $row['do'],
                ];
            }
        }

        return $cases;
    }

    public function testTheAgreementFixturesAreNonEmptyAndCoverBothEditions(): void
    {
        $rows = $this->agreementRows();
        $editions = [];
        foreach ($rows as $case) {
            $editions[$case[0]] = true;
        }

        self::assertGreaterThanOrEqual(15, count($rows), 'Expected a representative set of oracle rows.');
        self::assertArrayHasKey(RubricSystem::DIVINO_AFFLATU, $editions, '1954 rows missing.');
        self::assertArrayHasKey(RubricSystem::RUBRICAE_1955, $editions, '1955 rows missing.');
    }

    public function testTheSemidoubleReductionShowsInTheSameSaintAcrossEditions(): void
    {
        // St Alexius (17 Jul) is the paired witness to Title II.20: a semidouble under 1954,
        // reduced to a simple under 1955 — the same feast, celebrated under both, at the two
        // grades, proving the derive end to end.
        $da = DayResolver::forEdition(RubricSystem::divinoAfflatu())
            ->resolveDay(new DateTimeImmutable('1954-07-17', new DateTimeZone('UTC')));
        $cn = DayResolver::forEdition(RubricSystem::rubricae1955())
            ->resolveDay(new DateTimeImmutable('1958-07-17', new DateTimeZone('UTC')));

        self::assertSame('semiduplex', self::gradeOf($da), 'Alexius is a semidouble under 1954');
        self::assertSame('simplex', self::gradeOf($cn), 'Alexius is a simple under 1955');
    }

    public function testKnownDifferencesAreWellFormed(): void
    {
        $rows = $this->readNdjson('known-differences.ndjson');
        self::assertNotSame([], $rows, 'The known-differences catalogue is empty.');

        foreach ($rows as $row) {
            foreach (['edition', 'example', 'category', 'note'] as $field) {
                self::assertArrayHasKey($field, $row, "Known-difference row missing '$field'.");
            }
            self::assertContains($row['category'], self::CATEGORIES, "Unknown category: {$row['category']}");
        }
    }

    private static function gradeOf(\Directorium\Core\Calendar\LiturgicalDay $day): ?string
    {
        $celebration = $day->celebration();
        if ($celebration === [] || !$celebration[0] instanceof SanctoralObservance) {
            return null;
        }
        $legacyRank = $celebration[0]->legacyRank();

        return $legacyRank !== null ? $legacyRank->value() : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNdjson(string $file): array
    {
        $raw = file_get_contents(self::FIXTURES . '/' . $file);
        self::assertIsString($raw, "Missing fixture: $file");

        $rows = [];
        foreach (array_filter(explode("\n", trim($raw))) as $line) {
            /** @var array<string, mixed> $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $rows[] = $row;
        }

        return $rows;
    }
}
