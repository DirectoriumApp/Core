<?php

/**
 * Regenerate the SSPX class-difference baseline (#45 / #49).
 *
 * The baseline at tests/Validation/fixtures/sspx/sspx-differences.ndjson pins the
 * current set of engine ↔ SSPX class mismatches — see {@see SspxOracle}. The
 * validation test asserts the live mismatches equal it exactly, so a regression and a
 * drift both fail until reviewed. SSPX is a particular calendar, so these mismatches
 * are expected; the baseline is the living catalogue of how the base engine relates to
 * SSPX (corroborated base-1962 ranks, and the particular differences that seed the
 * v0.2 overlay). Run this only after a reviewed change to the resolved calendar or a
 * refreshed fixture, then commit the regenerated baseline alongside that change.
 *
 * Usage: php bin/freeze-sspx-baseline.php
 */

declare(strict_types=1);

use Introibo\Core\Tests\Validation\SspxOracle;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = SspxOracle::baselinePath();
$rows = SspxOracle::divergences();
file_put_contents($path, SspxOracle::freezeText());

fwrite(STDOUT, sprintf('Froze %d SSPX class differences to %s%s', count($rows), $path, PHP_EOL));
