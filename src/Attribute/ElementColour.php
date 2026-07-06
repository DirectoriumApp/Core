<?php

declare(strict_types=1);

namespace Directorium\Core\Attribute;

/**
 * The liturgical colour borne by a single celebration *element* — the principal
 * office or one of its commemorations.
 *
 * A day is not a single colour: the principal and each commemoration may each
 * wear a different colour, so colour is modelled per element, not per day. The
 * element carrying the colour (principal vs. a specific commemoration) is
 * composed elsewhere; this value object is just the colour that element wears.
 *
 * Rose is never a *base* colour. Gaudete and Laetare are violet days on which
 * rose vestments are permitted but not required, so rose is expressed as a
 * `roseAllowed` flag on a violet base — matching how the rubrics treat it.
 * Whether rose is actually worn is a celebrant's choice, not calendar data, so
 * no single "effective" colour is derived here.
 */
final class ElementColour
{
    private Colour $base;

    private bool $roseAllowed;

    private function __construct(Colour $base, bool $roseAllowed)
    {
        $this->base = $base;
        $this->roseAllowed = $roseAllowed;
    }

    /** A plain element of the given colour, with no rose option. */
    public static function of(Colour $base): self
    {
        return new self($base, false);
    }

    /** A violet element on which rose vestments are permitted (Gaudete / Laetare). */
    public static function violetWithRose(): self
    {
        return new self(Colour::violet(), true);
    }

    public function base(): Colour
    {
        return $this->base;
    }

    /** True only for the violet days on which rose is permitted. */
    public function roseAllowed(): bool
    {
        return $this->roseAllowed;
    }

    public function equals(self $other): bool
    {
        return $this->base->equals($other->base)
            && $this->roseAllowed === $other->roseAllowed;
    }
}
