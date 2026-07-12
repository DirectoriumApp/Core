<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Decree;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Decree\Decree;
use Directorium\Core\Decree\DecreedSanctoralData;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Overlay\RerankOperation;
use Directorium\Core\Sanctoral\SanctoralData;
use Directorium\Core\Tests\Fixture\SeedSanctoralData;
use PHPUnit\Framework\TestCase;

/**
 * The year-aware decree sanctoral decorator (#366): a dated rerank applied to the base
 * sanctoral only for the resolution years in which its feast falls on or after the
 * decree's effective date. St Lawrence (10 August, second class in the seed) is elevated
 * to first class by a test decree, so the gate is exact to the day.
 */
final class DecreedSanctoralDataTest extends TestCase
{
    private const LAURENCE = 'roman:sanctorale:laurentius';

    /** A decree elevating St Lawrence to first class, effective on the given date. */
    private static function elevateLaurence(string $effective): Decree
    {
        return new Decree(
            $effective . '-laurence',
            new DateTimeImmutable($effective . ' 00:00:00', new DateTimeZone('UTC')),
            'Test elevation of St Lawrence',
            [new RerankOperation(ObservanceId::parse(self::LAURENCE), RankClass::classI())],
            []
        );
    }

    private static function rankOf(SanctoralData $data, string $id): int
    {
        foreach ($data->entries() as $entry) {
            if ($entry->identity()->id()->toString() === $id) {
                return $entry->rank()->ordinal();
            }
        }

        self::fail(sprintf('No entry with id "%s".', $id));
    }

    /** St Lawrence's realized rank with the decree in force (or not) for the resolution year. */
    private static function laurenceRank(Decree $decree, int $year): int
    {
        return self::rankOf(new DecreedSanctoralData(new SeedSanctoralData(), [$decree], $year), self::LAURENCE);
    }

    public function testAppliesFromTheEffectiveYear(): void
    {
        // Effective 1 June 2020 — before St Lawrence's 10 August date. The rerank is not in force
        // in 2019 (his day precedes the effective date) but is in 2020 and after.
        $decree = self::elevateLaurence('2020-06-01');

        self::assertSame(2, self::laurenceRank($decree, 2019));
        self::assertSame(1, self::laurenceRank($decree, 2020));
        self::assertSame(1, self::laurenceRank($decree, 2025));
    }

    public function testGatesPerDateWithinTheEffectiveYear(): void
    {
        // Effective 1 September 2020 — AFTER St Lawrence's 10 August date, so even in the effective
        // year 2020 his day (10 Aug) precedes it and the rerank does not apply; it applies from 2021.
        $decree = self::elevateLaurence('2020-09-01');

        self::assertSame(2, self::laurenceRank($decree, 2020));
        self::assertSame(1, self::laurenceRank($decree, 2021));
    }

    public function testNoDecreesLeavesTheBaseUnchanged(): void
    {
        $decorated = new DecreedSanctoralData(new SeedSanctoralData(), [], 2025);

        self::assertSame(2, self::rankOf($decorated, self::LAURENCE), 'St Lawrence keeps his seed rank');
        self::assertCount(count((new SeedSanctoralData())->entries()), $decorated->entries());
    }

    public function testVersionIsTheBaseUnchanged(): void
    {
        $decorated = new DecreedSanctoralData(new SeedSanctoralData(), [self::elevateLaurence('2020-06-01')], 2025);

        self::assertSame((new SeedSanctoralData())->version(), $decorated->version());
    }
}
