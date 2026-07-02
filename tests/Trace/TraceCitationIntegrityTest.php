<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Trace;

use DateInterval;
use DateTimeImmutable;
use Introibo\Core\Corpus\Corpus;
use PHPUnit\Framework\TestCase;

use function Introibo\Core\explain;

/**
 * Citation integrity for the resolution trace (#233/#236): a reason is only
 * authoritative if it cites its rule, and a citation is only authoritative if it
 * foreign-keys a real source. This sweeps the trace across whole years and proves
 * that every cited step resolves into the source registry (`sources.ndjson`), so no
 * reason can ship a dangling citation. The only step that may go uncited is the
 * structural `no-temporal-season`, which honestly has no governing rubric.
 */
final class TraceCitationIntegrityTest extends TestCase
{
    public function testEveryTraceCitationForeignKeysAKnownSource(): void
    {
        $known = self::knownSourceKeys();

        $checked = 0;
        foreach (self::sweepSteps() as [$date, $rule, $citation]) {
            if ($citation === null) {
                continue;
            }
            $key = explode(':', $citation, 2)[0];
            self::assertArrayHasKey(
                $key,
                $known,
                sprintf('%s: step "%s" cites unknown source "%s"', $date, $rule, $citation)
            );
            $checked++;
        }

        self::assertGreaterThan(1000, $checked, 'Expected many cited trace steps across the swept years.');
    }

    public function testTheOnlyUncitedStepIsTheStructuralNoSeasonReason(): void
    {
        foreach (self::sweepSteps() as [$date, $rule, $citation]) {
            if ($citation === null) {
                $message = sprintf('%s: unexpected uncited step "%s"', $date, $rule);
                self::assertSame('no-temporal-season', $rule, $message);
            }
        }

        self::addToAssertionCount(1);
    }

    /**
     * @return array<string, true>
     */
    private static function knownSourceKeys(): array
    {
        $keys = [];
        foreach (Corpus::default()->records('sources.ndjson') as $row) {
            $keys[(string) $row['key']] = true;
        }

        return $keys;
    }

    /**
     * Every reasoned step of every day's trace across a couple of full years, as
     * (date, rule, citation) triples. A generator so the whole sweep is never held in
     * memory at once.
     *
     * @return iterable<array{0: string, 1: string, 2: string|null}>
     */
    private static function sweepSteps(): iterable
    {
        $oneDay = new DateInterval('P1D');
        foreach (['2024', '2025'] as $year) {
            $date = new DateTimeImmutable($year . '-01-01');
            $end = new DateTimeImmutable($year . '-12-31');
            for (; $date <= $end; $date = $date->add($oneDay)) {
                $resolution = explain($date)['resolution'];
                if (!is_array($resolution)) {
                    continue;
                }
                $iso = $date->format('Y-m-d');
                foreach (['winner', 'commemorationLimit', 'colour', 'season'] as $section) {
                    yield [$iso, (string) $resolution[$section]['rule'], $resolution[$section]['citation']];
                }
                foreach ($resolution['losers'] as $loser) {
                    yield [$iso, (string) $loser['rule'], $loser['citation']];
                }
            }
        }
    }
}
