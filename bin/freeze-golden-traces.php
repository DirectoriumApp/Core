<?php

/**
 * Regenerate the golden-master resolution-trace fixture (#233 / #240).
 *
 * The fixture at tests/Trace/fixtures/golden-traces.ndjson pins the full explanation
 * of a curated set of contested days — see {@see GoldenTrace}. The regression test
 * asserts the live traces equal it exactly, so any drift in the engine's *reasoning*
 * (even one that leaves the winner unchanged) fails until reviewed. Run this only after
 * a reviewed change to the resolution or its explanation, then commit the regenerated
 * fixture with that change; the diff is the record of what the engine now explains
 * differently.
 *
 * Usage: php bin/freeze-golden-traces.php
 */

declare(strict_types=1);

use Directorium\Core\Tests\Trace\GoldenTrace;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = GoldenTrace::fixturePath();
file_put_contents($path, GoldenTrace::freezeText());

fwrite(STDOUT, sprintf('Froze %d golden traces to %s%s', count(GoldenTrace::CONTESTED), $path, PHP_EOL));
