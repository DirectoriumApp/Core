<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Corpus;

use DateInterval;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Overlay\CalendarCatalog;
use Directorium\Core\Temporal\TemporalCalendar;
use PHPUnit\Framework\TestCase;

/**
 * The process-wide corpus cache must never change resolved output (#96): a year
 * resolved from the warm cache is byte-identical to the same year resolved after
 * {@see Corpus::flush()} reparses the corpus from disk. This is the "cache correctness
 * asserted against uncached output" guarantee — the golden suite proves the output is
 * stable, this proves caching does not perturb it.
 */
final class CacheConsistencyTest extends TestCase
{
    /**
     * @dataProvider editions
     */
    public function testColdAndWarmCorpusCacheResolveIdentically(?string $edition): void
    {
        $catalog = new CalendarCatalog();

        // Warm the process-wide cache, then digest a full year read from it.
        $catalog->resolver(null, $edition)->resolveYear(2024);
        $warm = $this->yearDigest($catalog, $edition, 2024);

        // Drop the caches so the next resolution reparses the corpus from disk (cold).
        Corpus::flush();
        $cold = $this->yearDigest($catalog, $edition, 2024);

        self::assertSame($warm, $cold, 'the corpus cache must not change resolved output');
    }

    /** @return array<string, array{string|null}> */
    public function editions(): array
    {
        return [
            'universal 1962' => [null],
            'divino afflatu 1954' => [RubricSystem::DIVINO_AFFLATU],
        ];
    }

    /** Flushing an already-empty cache is safe and resolution still works afterwards. */
    public function testFlushIsIdempotentAndResolutionSurvivesIt(): void
    {
        Corpus::flush();
        Corpus::flush();

        $resolved = (new CalendarCatalog())->resolver(null, null)->resolveYear(2024);
        $contract = DayContract::from(
            $resolved->day(TemporalCalendar::utcDate(2024, 12, 25)),
            $resolved->provenance()
        )->toArray();

        self::assertSame('roman:temporale:christmas:nativity', $contract['celebration'][0]['id']);
    }

    /** A sha256 over every day's contract in the year — a byte-level fingerprint of the resolution. */
    private function yearDigest(CalendarCatalog $catalog, ?string $edition, int $year): string
    {
        $resolved = $catalog->resolver(null, $edition)->resolveYear($year);
        $provenance = $resolved->provenance();

        $date = TemporalCalendar::utcDate($year, 1, 1);
        $end = TemporalCalendar::utcDate($year, 12, 31);
        $oneDay = new DateInterval('P1D');

        $hash = hash_init('sha256');
        while ($date <= $end) {
            $contract = DayContract::from($resolved->day($date), $provenance)->toArray();
            hash_update($hash, json_encode($contract, JSON_THROW_ON_ERROR));
            $date = $date->add($oneDay);
        }

        return hash_final($hash);
    }
}
