<?php

declare(strict_types=1);

namespace Directorium\Core;

/**
 * Package facade for the Directorium liturgical engine.
 *
 * This class anchors the PSR-4 namespace for the library and serves as the
 * stable public entry point. The calendar-resolution API — most notably the
 * `day()` method — is attached here as the engine is built out (issue #13).
 *
 * The engine is clean-room: no code originates from any prior calendar project.
 */
final class Directorium
{
    /**
     * Human-readable identifier for the library.
     *
     * Present so the PSR-4 autoloader has a resolvable symbol to smoke-test
     * against before any engine behaviour exists.
     */
    public const NAME = 'directorium/core';

    /**
     * The engine (resolver) version, one of the three axes of the output
     * contract's provenance.
     *
     * This tracks the resolver's behaviour, not the corpus data or the contract
     * shape — it is hand-bumped whenever a change would alter the resolved
     * output for identical inputs, so a consumer keying a cache on
     * `(edition, corpusVersion, engineVersion)` re-reads when the engine moves.
     * See {@see \Directorium\Core\Contract\Provenance}.
     */
    public const VERSION = '0.4.0';
}
