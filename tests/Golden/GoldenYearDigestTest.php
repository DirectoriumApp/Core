<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Golden;

use PHPUnit\Framework\TestCase;

/**
 * The corpus-swap safety gate (#365).
 *
 * This test re-resolves every civil year the engine can produce and asserts its
 * liturgy is byte-identical to the committed golden fixture. It is the guard the
 * data-for-code swaps run against: the sanctoral (#41), temporal (#42), and
 * precedence (#43) datasets replace inline PHP with generated data, and each must
 * leave this fixture untouched. A refactor that moves the resolved calendar by a
 * single byte fails here; an intended change (such as #41 expanding the sanctoral
 * from the seed slice to the full calendar) is accepted by re-running
 * `php bin/freeze-golden-year.php` and committing the regenerated fixture.
 *
 * @see GoldenYear
 */
final class GoldenYearDigestTest extends TestCase
{
    /**
     * The fixture is a contiguous, ascending run of every resolvable year, with
     * no gaps, duplicates, or strays — checked without resolving anything so a
     * malformed fixture reports clearly before the heavy digest pass.
     */
    public function testFixtureCoversEveryResolvableYearContiguously(): void
    {
        $fixture = $this->loadFixture();

        $expected = GoldenYear::LAST_YEAR - GoldenYear::FIRST_YEAR + 1;
        self::assertCount($expected, $fixture, 'Fixture year count does not match the resolvable range.');

        $years = array_keys($fixture);
        self::assertSame(
            range(GoldenYear::FIRST_YEAR, GoldenYear::LAST_YEAR),
            $years,
            'Fixture years must be contiguous and ascending.'
        );
    }

    /**
     * The gate itself: every year's freshly resolved digest must equal the frozen
     * one. Mismatches are collected so the failure names the offending years
     * rather than aborting on the first.
     */
    public function testEveryYearResolvesToItsFrozenDigest(): void
    {
        $fixture = $this->loadFixture();

        $mismatches = [];
        foreach ($fixture as $year => $frozen) {
            $days = GoldenYear::dayCount($year);
            $sha = GoldenYear::digestFor($year);
            if ($days !== $frozen['days'] || $sha !== $frozen['sha256']) {
                $mismatches[$year] = sprintf(
                    '%d: expected %d days / %s, got %d days / %s',
                    $year,
                    $frozen['days'],
                    $frozen['sha256'],
                    $days,
                    $sha
                );
            }
        }

        self::assertSame([], array_values($mismatches), sprintf(
            "%d year(s) drifted from the golden fixture. If the change is intended, re-run "
            . "`php bin/freeze-golden-year.php` and commit the result.\n  %s",
            count($mismatches),
            implode("\n  ", array_slice($mismatches, 0, 10))
        ));
    }

    /**
     * Parse the committed fixture into year => {days, sha256}, keyed in file
     * order.
     *
     * @return array<int, array{days: int, sha256: string}>
     */
    private function loadFixture(): array
    {
        $path = GoldenYear::fixturePath();
        self::assertFileExists($path, 'Golden fixture is missing; run `php bin/freeze-golden-year.php`.');

        $raw = file_get_contents($path);
        self::assertIsString($raw);

        $out = [];
        foreach (explode("\n", trim($raw)) as $line) {
            /** @var array{year: int, days: int, sha256: string} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $out[$row['year']] = ['days' => $row['days'], 'sha256' => $row['sha256']];
        }

        return $out;
    }
}
