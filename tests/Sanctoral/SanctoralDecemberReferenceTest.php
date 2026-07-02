<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Sanctoral;

use Introibo\Core\Sanctoral\CorpusSanctoralData;
use Introibo\Core\Sanctoral\SanctoralEntry;
use PHPUnit\Framework\TestCase;

/**
 * A spot-check of one sample month (December) against an independent reference
 * (#41): a hand-authored table of well-known December feasts of the 1962 General
 * Roman Calendar, each with its 1960 class, colour, and kind, asserted against
 * the generated corpus. It confirms the corpus data for the month and guards it
 * against silent regression; the full multi-oracle re-proving is issue #45.
 */
final class SanctoralDecemberReferenceTest extends TestCase
{
    /**
     * December 1962, day => [observance id, rank ordinal, colour, kind].
     * Fixed feasts of the Lord and octave days (Nativity Dec 25, its octave) are
     * temporal, not sanctoral, and are intentionally absent here.
     *
     * @return array<int, array{string, int, string, string}>
     */
    private const REFERENCE = [
        2 => ['roman:sanctorale:bibiana', 3, 'red', 'feast'],
        3 => ['roman:sanctorale:franciscus-xaverius', 3, 'white', 'feast'],
        4 => ['roman:sanctorale:petrus-chrysologus', 3, 'white', 'feast'],
        6 => ['roman:sanctorale:nicolaus', 3, 'white', 'feast'],
        7 => ['roman:sanctorale:ambrosius', 3, 'white', 'feast'],
        8 => ['roman:sanctorale:immaculata-conceptio', 1, 'white', 'feast'],
        13 => ['roman:sanctorale:lucia', 3, 'red', 'feast'],
        21 => ['roman:sanctorale:thomas-apostolus', 2, 'red', 'feast'],
        26 => ['roman:sanctorale:stephanus', 2, 'red', 'feast'],
        27 => ['roman:sanctorale:ioannes-evangelista', 2, 'white', 'feast'],
        28 => ['roman:sanctorale:innocentes', 2, 'red', 'feast'],
        29 => ['roman:sanctorale:thomas-becket', 3, 'red', 'feast'],
        31 => ['roman:sanctorale:silvester', 3, 'white', 'feast'],
    ];

    public function testDecemberMatchesTheReference(): void
    {
        $byDay = $this->decemberByDay();

        foreach (self::REFERENCE as $day => [$id, $rank, $colour, $kind]) {
            $entry = $this->entryOn($byDay, $day, $id);
            self::assertInstanceOf(SanctoralEntry::class, $entry, "December $day expected {$id}");

            self::assertSame($rank, $entry->rank()->ordinal(), "rank of {$id}");
            self::assertSame($colour, $entry->colour()->base()->value(), "colour of {$id}");
            self::assertSame($kind, $entry->identity()->kind()->value(), "kind of {$id}");
        }
    }

    /**
     * @param array<int, list<SanctoralEntry>> $byDay
     */
    private function entryOn(array $byDay, int $day, string $id): ?SanctoralEntry
    {
        foreach ($byDay[$day] ?? [] as $entry) {
            if ($entry->identity()->id()->toString() === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return array<int, list<SanctoralEntry>>
     */
    private function decemberByDay(): array
    {
        $byDay = [];
        foreach ((new CorpusSanctoralData())->entries() as $entry) {
            if ($entry->month() === 12) {
                $byDay[$entry->day()][] = $entry;
            }
        }

        return $byDay;
    }
}
