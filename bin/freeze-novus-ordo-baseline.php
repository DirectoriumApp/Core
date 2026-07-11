<?php

/**
 * Regenerate the Novus-Ordo known-differences baseline (#45 / #260).
 *
 * The baseline at tests/Validation/fixtures/novus-ordo/known-differences.ndjson pins the
 * current set of engine ↔ oracle divergences against the two independent engines (LitCal,
 * calapi) — see {@see NovusOrdoOracle}. The validation test asserts the live divergences
 * equal it exactly, so both a regression and an accuracy improvement fail until reviewed.
 * Run this only after a reviewed change to the resolved Novus-Ordo calendar (an
 * engine/corpus fix, or refreshed oracle fixtures), then commit the regenerated baseline
 * with that change; the diff is the record of what got better or worse.
 *
 * Usage: php bin/freeze-novus-ordo-baseline.php
 */

declare(strict_types=1);

use Directorium\Core\Tests\Validation\NovusOrdoOracle;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = NovusOrdoOracle::baselinePath();
$rows = NovusOrdoOracle::divergences();
file_put_contents($path, NovusOrdoOracle::freezeText());

fwrite(STDOUT, sprintf('Froze %d known differences to %s%s', count($rows), $path, PHP_EOL));
