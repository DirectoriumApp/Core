<?php

declare(strict_types=1);

namespace Directorium\Core\Contract;

/**
 * The three provenance axes stamped on every serialised day: which rubric
 * edition resolved it, which corpus of calendar data it drew on, and which
 * engine version produced it.
 *
 * These are deliberately independent. The **edition** is the rules-family that
 * governs precedence and ranks (v0.1.0: only `roman:rubricae-1960`). The
 * **corpus version** identifies the body of dated feast data, and carries no
 * edition token — the same 1962 corpus can be resolved under different editions.
 * The **engine version** ({@see \Directorium\Core\Directorium::VERSION}) tracks the
 * resolver's behaviour. A consumer keys a cache on all three: any one moving
 * means the resolved output may differ. See docs/design/output-contract.md.
 *
 * Immutable: three invariant strings.
 */
final class Provenance
{
    private string $edition;

    private string $corpusVersion;

    private string $engineVersion;

    public function __construct(string $edition, string $corpusVersion, string $engineVersion)
    {
        $this->edition = $edition;
        $this->corpusVersion = $corpusVersion;
        $this->engineVersion = $engineVersion;
    }

    /** The rules-family that resolved the day, e.g. `roman:rubricae-1960`. */
    public function edition(): string
    {
        return $this->edition;
    }

    /** The calendar-data corpus, e.g. `1962-seed`; never carries an edition token. */
    public function corpusVersion(): string
    {
        return $this->corpusVersion;
    }

    /** The resolver version that produced the result. */
    public function engineVersion(): string
    {
        return $this->engineVersion;
    }

    public function equals(self $other): bool
    {
        return $this->edition === $other->edition
            && $this->corpusVersion === $other->corpusVersion
            && $this->engineVersion === $other->engineVersion;
    }
}
