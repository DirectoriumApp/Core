<?php

declare(strict_types=1);

namespace Introibo\Core\Observance;

use InvalidArgumentException;

/**
 * The immutable identity of a liturgical observance.
 *
 * Grammar: `<rite>:<cycle>:<body>` — e.g. `roman:sanctorale:laurentius` or
 * `roman:temporale:advent:sunday-3`.
 *
 * The slug carries identity ONLY: never rank, colour, octave, or date. Those are
 * per-edition attributes or per-year realization, so one identifier stays stable
 * across every year and every rubric edition. Temporal days are addressed
 * structurally; the Easter-offset form is not a valid identifier.
 */
final class ObservanceId
{
    private const PATTERN = '/^roman:(temporale|sanctorale|votive):[a-z0-9]+(-[a-z0-9]+)*(:[a-z0-9]+(-[a-z0-9]+)*)*$/';

    /**
     * The Easter-offset addressing form is deliberately not an identifier
     * (structural temporal IDs only); its presence in a slug is rejected.
     */
    private const OFFSET_TOKEN = 'easter-offset';

    private Rite $rite;

    private Cycle $cycle;

    /** @var non-empty-list<string> Body segments after the cycle. */
    private array $body;

    private string $canonical;

    /**
     * @param non-empty-list<string> $body
     */
    private function __construct(Rite $rite, Cycle $cycle, array $body)
    {
        $this->rite = $rite;
        $this->cycle = $cycle;
        $this->body = $body;
        $this->canonical = $rite->value() . ':' . $cycle->value() . ':' . implode(':', $body);
    }

    public static function parse(string $slug): self
    {
        if (preg_match(self::PATTERN, $slug) !== 1) {
            throw new InvalidArgumentException(sprintf('Malformed ObservanceId: "%s"', $slug));
        }

        $parts = explode(':', $slug);
        $rite = Rite::fromString($parts[0]);
        $cycle = Cycle::fromString($parts[1]);
        $body = array_values(array_slice($parts, 2));
        if ($body === []) {
            // Unreachable given the pattern, but keeps the body type non-empty.
            throw new InvalidArgumentException(sprintf('ObservanceId has no body: "%s"', $slug));
        }

        if ($cycle->isTemporale()) {
            // The first body segment must name a real anchor family…
            AnchorFamily::fromString($body[0]);
            // …and the Easter-offset form may never be used as an identifier.
            if (in_array(self::OFFSET_TOKEN, $body, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Easter-offset addressing is not a valid identifier '
                    . '(structural temporal IDs only): "%s"',
                    $slug
                ));
            }
        }

        return new self($rite, $cycle, $body);
    }

    /**
     * Build from ordered segments, e.g. `of('roman', 'sanctorale', 'laurentius')`.
     */
    public static function of(string ...$segments): self
    {
        return self::parse(implode(':', $segments));
    }

    public function rite(): Rite
    {
        return $this->rite;
    }

    public function cycle(): Cycle
    {
        return $this->cycle;
    }

    /**
     * The temporal anchor family, or null for sanctorale/votive identifiers.
     */
    public function anchorFamily(): ?AnchorFamily
    {
        if (!$this->cycle->isTemporale()) {
            return null;
        }

        return AnchorFamily::fromString($this->body[0]);
    }

    /**
     * The body segments after the cycle.
     *
     * @return non-empty-list<string>
     */
    public function segments(): array
    {
        return $this->body;
    }

    public function toString(): string
    {
        return $this->canonical;
    }

    public function equals(self $other): bool
    {
        return $this->canonical === $other->canonical;
    }

    public function __toString(): string
    {
        return $this->canonical;
    }
}
