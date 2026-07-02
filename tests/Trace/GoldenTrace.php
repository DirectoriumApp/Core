<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Trace;

use DateTimeImmutable;

use function Introibo\Core\explain;

/**
 * Golden-master resolution traces for the hardest days (#233/#240).
 *
 * The golden-year digest (#365) pins what the engine resolves; this pins *why* — the
 * full explanation of a curated set of contested days (transfers, the Triduum, a
 * first-class feast occurring with a first-class Sunday, an office yielding without
 * commemoration, a commemoration-grade saint). If a precedence change ever alters the
 * reasoning — even one that leaves the winner unchanged — the frozen trace moves and
 * the regression test fails, so silent drift in the engine's justification is caught.
 *
 * The fixture is regenerated with bin/freeze-golden-traces.php after a reviewed change,
 * and the diff is the record of what the engine now explains differently.
 */
final class GoldenTrace
{
    /**
     * The pinned days, each chosen for the reasoning it exercises.
     *
     * @var array<string, string>
     */
    public const CONTESTED = [
        '2024-01-06' => 'Epiphany — a greatest-tier feast, no competitors',
        '2024-03-25' => 'Annunciation impeded into Holy Week — a first-class transfer (n. 95)',
        '2024-03-28' => 'Maundy Thursday — a coincident saint omitted, the Triduum admits none',
        '2024-03-31' => 'Easter Sunday — the paschal solemnity',
        '2024-06-20' => 'St Silverius — a commemoration-grade saint yields to the feria (#424)',
        '2024-08-15' => 'Assumption — an ordinary feria yields without commemoration (n. 108)',
        '2024-11-01' => 'All Saints — a first-class transfer and a feria omission together',
        '2024-12-08' => 'Immaculate Conception on the 2nd Sunday of Advent — two first-class offices',
        '2024-12-24' => 'Christmas Vigil — a first-class vigil in Advent',
        '2024-12-25' => 'Christmas — the Nativity',
        '2025-04-11' => 'Seven Sorrows in Passiontide — a saint commemorated (Leo the Great)',
    ];

    public static function fixturePath(): string
    {
        return __DIR__ . '/fixtures/golden-traces.ndjson';
    }

    /** The fixture text: one `{date, resolution}` record per contested day, in order. */
    public static function freezeText(): string
    {
        $out = '';
        foreach (array_keys(self::CONTESTED) as $date) {
            $record = ['date' => $date, 'resolution' => self::resolutionFor($date)];
            $out .= json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        }

        return $out;
    }

    /**
     * The resolution trace of one contested day.
     *
     * @return array<string, mixed>|null
     */
    public static function resolutionFor(string $date): ?array
    {
        /** @var array<string, mixed>|null $resolution */
        $resolution = explain(new DateTimeImmutable($date))['resolution'];

        return $resolution;
    }
}
