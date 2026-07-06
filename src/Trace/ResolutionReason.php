<?php

declare(strict_types=1);

namespace Directorium\Core\Trace;

use Directorium\Core\Citation\Citation;

/**
 * One reasoned step of a {@see ResolutionTrace} (#233): why the engine did what it
 * did, in words a human can read and with the rubric it did it under.
 *
 * A reason is three things: a stable machine `rule` key (so consumers can branch or
 * translate without parsing prose), a human `summary`, and an optional
 * {@see Citation} to the governing rubric or decree — the same citation model the
 * corpus uses (#44), foreign-keying into `sources.ndjson`. The citation is optional
 * only where no single rubric governs a step; it is left null rather than invented.
 *
 * Reasons are produced at the exact point the decision is made (see
 * {@see \Directorium\Core\Precedence\PrecedenceRules}), so the explanation can never
 * drift from the resolution it explains.
 */
final class ResolutionReason
{
    private string $rule;

    private string $summary;

    private ?Citation $citation;

    private function __construct(string $rule, string $summary, ?Citation $citation)
    {
        $this->rule = $rule;
        $this->summary = $summary;
        $this->citation = $citation;
    }

    /** A reason citing the rubric it rests on, e.g. `('n95-first-class-transfer', '…', 'rg-1960:95')`. */
    public static function cited(string $rule, string $summary, string $citationRef): self
    {
        return new self($rule, $summary, Citation::parse($citationRef));
    }

    /** A reason with no single governing rubric — the citation is honestly left absent. */
    public static function uncited(string $rule, string $summary): self
    {
        return new self($rule, $summary, null);
    }

    public function rule(): string
    {
        return $this->rule;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function citation(): ?Citation
    {
        return $this->citation;
    }

    /** The citation reference (`key` or `key:locator`), or null when uncited. */
    public function citationRef(): ?string
    {
        return $this->citation !== null ? $this->citation->toString() : null;
    }

    /**
     * @return array{rule: string, summary: string, citation: string|null}
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'summary' => $this->summary,
            'citation' => $this->citationRef(),
        ];
    }
}
