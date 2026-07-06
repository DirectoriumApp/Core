<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use RuntimeException;

/**
 * The optional Divinum Officium adapter for the validation harness (#45 / #50).
 *
 * Divinum Officium (https://github.com/DivinumOfficium/divinum-officium) is a
 * respected, independent Perl implementation of the traditional office and calendar.
 * A local DO checkout is a valuable *tertiary* cross-check on top of the base
 * authority (missalemeum, {@see MissalemeumOracle}) and the SSPX witness
 * ({@see SspxOracle}) — but Perl and a DO checkout are heavyweight, so this oracle is
 * deliberately OPTIONAL: it runs only when a checkout is configured and skips
 * gracefully otherwise, keeping the core suite free of any Perl requirement.
 *
 * Enable it by pointing DIVINUM_OFFICIUM_PATH at a DO checkout (see
 * tools/oracles/README-divinum-officium.md). When available, {@see resolveClass()}
 * invokes DO's own headless office script through the bridge
 * ({@see tools/oracles/divinum-officium-bridge.pl}) and {@see extractClass()} reads
 * the day's 1960 class from the output.
 *
 * The split of responsibilities is deliberate and honest: {@see isAvailable()}, the
 * graceful-skip behaviour, and the pure {@see extractClass()} parser are exercised in
 * CI; the live end-to-end comparison necessarily runs only where DO is installed, so
 * it is maintainer-verified. {@see DivinumOfficiumOracleTest} guards the parser so it
 * cannot silently rot, and fails loudly (rather than skipping) if DO is present but its
 * output no longer matches the parser.
 *
 * @see DivinumOfficiumOracleTest
 */
final class DivinumOfficiumOracle
{
    private const BRIDGE = __DIR__ . '/../../tools/oracles/divinum-officium-bridge.pl';

    /** The DO version string for the edition our engine implements. */
    private const VERSION = 'Rubrics 1960 - 1960';

    private const ROMAN = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4];

    /**
     * A small, liturgically unambiguous sample where any correct 1960 engine must
     * agree — great feasts plus two guard days for the bugs the harness caught
     * (2024-03-28 Maundy Thursday, #422; 2024-06-20 a commemoration-grade saint that
     * must yield to the feria, #424). Kept away from the corpus-rank grey area so a
     * maintainer's DO run is a clean pass/fail, not a re-run of the accuracy worklist.
     *
     * @return list<string>
     */
    public static function contestedDates(): array
    {
        return [
            '2024-01-06', // Epiphany — I classis
            '2024-03-28', // Maundy Thursday — I classis (Triduum, guards #422)
            '2024-03-31', // Easter Sunday — I classis
            '2024-05-19', // Pentecost — I classis
            '2024-06-20', // feria; St Silverius is commemoration-only (guards #424)
            '2024-08-15', // Assumption — I classis
            '2024-09-03', // St Pius X — III classis (base 1960; SSPX elevates)
            '2024-11-01', // All Saints — I classis
            '2024-12-25', // Nativity — I classis
        ];
    }

    public static function isAvailable(): bool
    {
        return self::checkout() !== null && self::perlBinary() !== null;
    }

    /** A human-readable reason the oracle is not runnable, for skip messages and docs. */
    public static function unavailableReason(): string
    {
        if (self::perlBinary() === null) {
            return 'perl was not found on PATH; install Perl to enable the Divinum Officium oracle.';
        }
        if (self::checkout() === null) {
            return 'set DIVINUM_OFFICIUM_PATH to a Divinum Officium checkout to enable this optional oracle '
                . '(see tools/oracles/README-divinum-officium.md).';
        }

        return 'the Divinum Officium oracle is available.';
    }

    /** Resolve the day's 1960 class via DO, or null when DO yields no class. */
    public static function resolveClass(string $date): ?int
    {
        return self::extractClass(self::runBridge($date));
    }

    /**
     * Read the 1960 class (1..4) from a Divinum Officium office. Pure and unit-tested:
     * this is the only fragile part of the integration, so it is isolated and covered.
     *
     * DO labels the day under the 1960 rubrics with the "N. classis" terminology; the
     * Roman form is authoritative, with an Arabic fallback. Returns null when no class
     * marker is present (e.g. a rank the sample should not include), which the live
     * test treats as "not comparable" rather than a failure.
     */
    public static function extractClass(string $officeOutput): ?int
    {
        if (preg_match('/\b(IV|III|II|I)\.?\s*classis\b/i', $officeOutput, $m) === 1) {
            return self::ROMAN[strtoupper($m[1])];
        }
        if (preg_match('/\b([1-4])\.?\s*classis\b/i', $officeOutput, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private static function runBridge(string $date): string
    {
        $checkout = self::checkout();
        $perl = self::perlBinary();
        if ($checkout === null || $perl === null) {
            throw new RuntimeException('Divinum Officium is not available: ' . self::unavailableReason());
        }

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            [$perl, self::BRIDGE, $checkout, $date, self::VERSION],
            $descriptors,
            $pipes
        );
        if (!\is_resource($process)) {
            throw new RuntimeException('Failed to launch the Divinum Officium bridge.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) {
            throw new RuntimeException(sprintf('Divinum Officium bridge failed (%d): %s', $status, trim($stderr)));
        }

        return $stdout;
    }

    private static function checkout(): ?string
    {
        $path = getenv('DIVINUM_OFFICIUM_PATH');
        if ($path === false || $path === '') {
            return null;
        }
        if (!is_dir($path) || !is_file($path . '/web/cgi-bin/horas/officium.pl')) {
            return null;
        }

        return $path;
    }

    private static function perlBinary(): ?string
    {
        $which = \DIRECTORY_SEPARATOR === '\\' ? 'where' : 'command -v';
        $output = @shell_exec($which . ' perl 2>' . (\DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null'));
        if (!is_string($output)) {
            return null;
        }
        $first = trim(strtok($output, "\n") ?: '');

        return $first === '' ? null : 'perl';
    }
}
