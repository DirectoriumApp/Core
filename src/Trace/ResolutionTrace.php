<?php

declare(strict_types=1);

namespace Introibo\Core\Trace;

/**
 * The show-your-work trace for one resolved day (#233): the whole field of offices
 * that competed, which one was celebrated and why, what became of each office it
 * beat, and how many commemorations the day admitted — every step reasoned and, where
 * a rubric governs it, cited.
 *
 * The trace is built by the resolver from the *actual* sorted candidates and the
 * *actual* outcomes ({@see \Introibo\Core\Precedence\DayResolver}), so it reports the
 * resolution as it was decided, not a reconstruction. It is opt-in: it fills the
 * output contract's reserved day-level `resolution` slot only when a caller asks to
 * explain a day, so the default contract stays lean and byte-stable. Tier positions
 * are carried as plain data (ordinal/line/selector), so this layer stays free of the
 * Precedence types that build it. Colour and season derivation (#235) extend this
 * shape additively. See docs/design/resolution-trace-model.md.
 *
 * @phpstan-type TraceTier array{ordinal: int, line: int|null, selector: string|null}
 * @phpstan-type TraceCandidate array{id: string, rank: int, kind: string, tier: TraceTier}
 * @phpstan-type TraceLoser array{id: string, outcome: string, reason: ResolutionReason}
 * @phpstan-type TraceWinner array{id: string, line: int|null, reason: ResolutionReason}
 */
final class ResolutionTrace
{
    /** @var TraceWinner */
    private array $winner;

    /** @var list<TraceCandidate> */
    private array $candidates;

    /** @var list<TraceLoser> */
    private array $losers;

    private int $commemorationLimit;

    private ResolutionReason $commemorationLimitReason;

    /**
     * @param TraceWinner          $winner
     * @param list<TraceCandidate> $candidates
     * @param list<TraceLoser>     $losers
     */
    public function __construct(
        array $winner,
        array $candidates,
        array $losers,
        int $commemorationLimit,
        ResolutionReason $commemorationLimitReason
    ) {
        $this->winner = $winner;
        $this->candidates = $candidates;
        $this->losers = $losers;
        $this->commemorationLimit = $commemorationLimit;
        $this->commemorationLimitReason = $commemorationLimitReason;
    }

    /**
     * The trace as the `resolution` slot's JSON-ready structure.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'winner' => ['id' => $this->winner['id'], 'line' => $this->winner['line']]
                + $this->winner['reason']->toArray(),
            'candidates' => array_map(
                static function (array $candidate): array {
                    return [
                        'id' => $candidate['id'],
                        'rank' => $candidate['rank'],
                        'kind' => $candidate['kind'],
                        'tier' => $candidate['tier'],
                    ];
                },
                $this->candidates
            ),
            'losers' => array_map(
                static function (array $loser): array {
                    return ['id' => $loser['id'], 'outcome' => $loser['outcome']]
                        + $loser['reason']->toArray();
                },
                $this->losers
            ),
            'commemorationLimit' => ['value' => $this->commemorationLimit]
                + $this->commemorationLimitReason->toArray(),
        ];
    }
}
